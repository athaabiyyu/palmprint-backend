import os
import urllib.request

import cv2
import mediapipe as mp
import numpy as np
from mediapipe.tasks import python as mp_python
from mediapipe.tasks.python import vision as mp_vision
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
from mediapipe.tasks.python.vision.core.vision_task_running_mode import (
    VisionTaskRunningMode,
)

# =============================================================================
# KONSTANTA & INISIALISASI MODEL
# =============================================================================

ROI_SIZE = 200
PALM_LANDMARK_IDS = [0, 1, 5, 9, 13, 17]
ALIGN_ANGLE_MAX = 5.0
ROI_SIZE_MIN = 80

_MODEL_PATH = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "hand_landmarker.task"
)
_MODEL_URL = (
    "https://storage.googleapis.com/mediapipe-models/"
    "hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task"
)


def _ensure_model():
    """Download model MediaPipe jika belum ada (±9 MB, sekali saja)."""
    if not os.path.exists(_MODEL_PATH):
        print(f"[roi_mediapipe] Downloading hand_landmarker.task (~9 MB)...")
        urllib.request.urlretrieve(_MODEL_URL, _MODEL_PATH)
        print(f"[roi_mediapipe] Model tersimpan: {_MODEL_PATH}")


_ensure_model()

_options = HandLandmarkerOptions(
    base_options=mp_python.BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode=VisionTaskRunningMode.IMAGE,
    num_hands=1,
    min_hand_detection_confidence=0.6,
    min_hand_presence_confidence=0.6,
    min_tracking_confidence=0.6,
)
_landmarker = HandLandmarker.create_from_options(_options)


# =============================================================================
# HELPER INTERNAL
# =============================================================================


def _detect(img_bgr: np.ndarray):
    """
    Safe Standard Detector: Konversi tipe data aman tanpa manipulasi kontras ekstrim
    yang bisa merusak deteksi bawaan MediaPipe pada file .tiff.
    """
    # 1. Jika input bertipe float atau 16-bit (sering terjadi pada .tiff), normalisasi ke 8-bit
    if img_bgr.dtype != np.uint8:
        img_8bit = cv2.normalize(img_bgr, None, 0, 255, cv2.NORM_MINMAX, dtype=cv2.CV_8U)
    else:
        img_8bit = img_bgr.copy()

    # 2. Pastikan gambar memiliki 3 channel (BGR)
    if len(img_8bit.shape) == 2:
        img_8bit = cv2.cvtColor(img_8bit, cv2.COLOR_GRAY2BGR)

    # 3. Konversi langsung BGR ke RGB standar MediaPipe (Tanpa CLAHE/Enhancement siluman)
    img_rgb = cv2.cvtColor(img_8bit, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    return _landmarker.detect(mp_image)

def _compute_angle(lm, w: int, h: int) -> float:
    wrist_x = lm[0].x * w
    wrist_y = lm[0].y * h
    mid_x   = lm[9].x * w
    mid_y   = lm[9].y * h

    if wrist_y <= mid_y:
        return 0.0

    dx = mid_x - wrist_x
    dy = mid_y - wrist_y
    angle = np.degrees(np.arctan2(dx, -dy))
    return float(np.clip(angle, -ALIGN_ANGLE_MAX, ALIGN_ANGLE_MAX))


def _rotate_image(img_bgr: np.ndarray, angle: float) -> np.ndarray:
    """Rotasi gambar dengan mempertahankan ukuran asli (w, h) sebagai pusat."""
    h, w = img_bgr.shape[:2]
    M = cv2.getRotationMatrix2D((w / 2, h / 2), angle, 1.0)
    rotated = cv2.warpAffine(
        img_bgr,
        M,
        (w, h),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_REPLICATE,
    )
    return rotated


def _compute_dynamic_roi_size(lm, w: int, h: int) -> int:
    index_mcp_x, index_mcp_y = lm[5].x * w, lm[5].y * h
    pinky_mcp_x, pinky_mcp_y = lm[17].x * w, lm[17].y * h

    knuckle_dist = np.sqrt((pinky_mcp_x - index_mcp_x) ** 2 + (pinky_mcp_y - index_mcp_y) ** 2)
    roi_size = int(np.clip(knuckle_dist * 1.15, ROI_SIZE_MIN, ROI_SIZE))
    return roi_size


def _compute_palm_center(lm, w: int, h: int) -> tuple[int, int]:
    index_mcp_x, index_mcp_y = lm[5].x * w, lm[5].y * h
    middle_mcp_x, middle_mcp_y = lm[9].x * w, lm[9].y * h
    ring_mcp_x, ring_mcp_y = lm[13].x * w, lm[13].y * h
    pinky_mcp_x, pinky_mcp_y = lm[17].x * w, lm[17].y * h
    wrist_x, wrist_y = lm[0].x * w, lm[0].y * h

    anchor_x = (index_mcp_x + middle_mcp_x + ring_mcp_x + pinky_mcp_x) / 4.0
    anchor_y = (index_mcp_y + middle_mcp_y + ring_mcp_y + pinky_mcp_y) / 4.0

    palm_width = np.sqrt((pinky_mcp_x - index_mcp_x) ** 2 + (pinky_mcp_y - index_mcp_y) ** 2)

    vx = pinky_mcp_x - index_mcp_x
    vy = pinky_mcp_y - index_mcp_y

    len_v = np.sqrt(vx**2 + vy**2)
    nx = -vy / len_v if len_v > 0 else 0
    ny = vx / len_v if len_v > 0 else 1

    # Kompas arah menuju wrist (Mendukung Tangan Kanan & Kiri secara otomatis)
    wx = wrist_x - anchor_x
    wy = wrist_y - anchor_y
    dot = nx * wx + ny * wy
    if dot < 0:
        nx = -nx
        ny = -ny

    # Pergeseran emas (0.52) ke tengah area telapak tangan
    offset = palm_width * 0.52
    
    cx = anchor_x + nx * offset
    cy = anchor_y + ny * offset

    return int(cx), int(cy)


def _crop_roi(
    gray: np.ndarray,
    cx: int,
    cy: int,
    size: int | None = None,
) -> tuple[np.ndarray, tuple[int, int, int, int]]:
    h, w = gray.shape[:2]
    size = size or ROI_SIZE
    half = size // 2

    x1 = max(cx - half, 0)
    y1 = max(cy - half, 0)
    x2 = min(cx + half, w)
    y2 = min(cy + half, h)
    roi = gray[y1:y2, x1:x2]

    pad_top = max(0, half - cy)
    pad_bottom = max(0, (cy + half) - h)
    pad_left = max(0, half - cx)
    pad_right = max(0, (cx + half) - w)

    if any([pad_top, pad_bottom, pad_left, pad_right]):
        roi = cv2.copyMakeBorder(
            roi,
            top=pad_top,
            bottom=pad_bottom,
            left=pad_left,
            right=pad_right,
            borderType=cv2.BORDER_REPLICATE,
        )

    if size != ROI_SIZE:
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE), interpolation=cv2.INTER_AREA)

    return roi, (x1, y1, x2, y2)


# =============================================================================
# FUNGSI INTI — SINKRONISASI PETA KOORDINAT AFINE
# =============================================================================


def _get_palm_centroid(img_bgr: np.ndarray):
    """
    Pipeline Tunggal: Hitung semua geometri di citra asli, lalu lakukan
    transformasi matriks affine pada koordinat pasca-rotasi gambar.
    """
    h, w = img_bgr.shape[:2]
    cx, cy = w // 2, h // 2
    angle = 0.0
    dynamic_size = ROI_SIZE

    result = _detect(img_bgr)

    if not result.hand_landmarks:
        return cx, cy, None, None, img_bgr, angle, dynamic_size, True

    lm = result.hand_landmarks[0]

    # 1. Ambil data rotasi & skala dasar dari gambar asli
    angle = _compute_angle(lm, w, h)
    dynamic_size = _compute_dynamic_roi_size(lm, w, h)
    palm_cx, palm_cy = _compute_palm_center(lm, w, h)

    # 2. Eksekusi rotasi gambar
    img_aligned = _rotate_image(img_bgr, angle)

    # 3. SINKRONISASI MATEMATIS: Putar koordinat pusat mengikuti rotasi matriks gambar
    M = cv2.getRotationMatrix2D((w / 2, h / 2), angle, 1.0)
    
    # Transformasikan titik pusat
    pt_center = np.array([[[palm_cx, palm_cy]]], dtype=np.float32)
    cx_rot, cy_rot = cv2.transform(pt_center, M)[0][0]
    
    half = dynamic_size // 2
    cx = int(np.clip(cx_rot, half, w - half))
    cy = int(np.clip(cy_rot, half, h - half))

    # Transformasikan juga seluruh landmark untuk keperluan gambar debug di notebook
    xs = [int(lm[i].x * w) for i in PALM_LANDMARK_IDS]
    ys = [int(lm[i].y * h) for i in PALM_LANDMARK_IDS]
    pts = np.array([[xs[i], ys[i]] for i in range(len(xs))], dtype=np.float32).reshape(-1, 1, 2)
    
    pts_rotated = cv2.transform(pts, M).squeeze().astype(np.int32)
    landmarks_rotated = [tuple(p) for p in pts_rotated]
    hull_rotated = cv2.convexHull(pts_rotated)

    return cx, cy, landmarks_rotated, hull_rotated, img_aligned, angle, dynamic_size, False


def extract_roi(img_bgr: np.ndarray) -> np.ndarray:
    cx, cy, _, __, img_aligned, _angle, dynamic_size, fallback = _get_palm_centroid(img_bgr)
    gray = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, _ = _crop_roi(gray, cx, cy, size=dynamic_size)
    return roi


def detect_palm_opencv(img_bgr: np.ndarray, debug: bool = False):
    """
    Drop-in replacement yang sudah sinkron antara visualisasi dan koordinat potong.
    """
    cx, cy, landmarks, hull, img_aligned, angle, dynamic_size, fallback = (
        _get_palm_centroid(img_bgr)
    )

    gray = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, roi_rect = _crop_roi(gray, cx, cy, size=dynamic_size)

    h, w = img_bgr.shape[:2]
    mask_vis = np.zeros((h, w), dtype=np.uint8)
    if hull is not None:
        cv2.fillConvexPoly(mask_vis, hull, 255)

    dbg = {
        "mask_raw": mask_vis,
        "mask_clean": mask_vis.copy(),
        "fallback": fallback,
        "contour": hull,
        "area": float(cv2.contourArea(hull)) if hull is not None else 0.0,
        "cx": cx,
        "cy": cy,
        "roi_rect": roi_rect,
        "landmarks": landmarks,
        "angle": angle,
        "img_aligned": img_aligned,
        "dynamic_roi_size": dynamic_size,
    }

    return roi, dbg


if __name__ == "__main__":
    # Bagian pengetesan file mandiri tetap dipertahankan seperti aslimu...
    pass