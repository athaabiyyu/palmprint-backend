"""
roi_mediapipe.py
=================
Drop-in replacement untuk fungsi ROI extraction di:
  1. palmprint_api.py         -> ganti fungsi extract_roi()
  2. palmprint_modeling.ipynb -> ganti fungsi detect_palm_opencv()

Kompatibel dengan MediaPipe >= 0.10 (API baru: HandLandmarker)
"""

import cv2
import numpy as np
import mediapipe as mp
from mediapipe.tasks import python as mp_python
from mediapipe.tasks.python import vision as mp_vision
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
from mediapipe.tasks.python.vision.core.vision_task_running_mode import VisionTaskRunningMode

import urllib.request
import os

# ─────────────────────────────────────────────
# DOWNLOAD MODEL MEDIAPIPE (otomatis sekali)
# ─────────────────────────────────────────────

_MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'hand_landmarker.task')
_MODEL_URL  = 'https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task'

def _ensure_model():
    if not os.path.exists(_MODEL_PATH):
        print(f'[roi_mediapipe] Downloading hand_landmarker.task (~9MB)...')
        urllib.request.urlretrieve(_MODEL_URL, _MODEL_PATH)
        print(f'[roi_mediapipe] Model saved: {_MODEL_PATH}')

_ensure_model()

# ─────────────────────────────────────────────
# INISIALISASI HAND LANDMARKER (API baru 0.10+)
# ─────────────────────────────────────────────

_options = HandLandmarkerOptions(
    base_options        = mp_python.BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode        = VisionTaskRunningMode.IMAGE,
    num_hands           = 1,
    min_hand_detection_confidence = 0.3,
    min_hand_presence_confidence  = 0.3,
    min_tracking_confidence       = 0.3,
)
_landmarker = HandLandmarker.create_from_options(_options)

# Landmark index area telapak (bukan ujung jari)
# 0=wrist, 1=thumb_cmc, 5=index_mcp, 9=middle_mcp, 13=ring_mcp, 17=pinky_mcp
PALM_LANDMARK_IDS = [0, 1, 5, 9, 13, 17]

ROI_SIZE = 200


# ═══════════════════════════════════════════════════════════════════
# FUNGSI HELPER INTERNAL
# ═══════════════════════════════════════════════════════════════════

def _get_palm_centroid(img_bgr):
    """
    Jalankan MediaPipe HandLandmarker, kembalikan (cx, cy, landmarks, hull).
    Jika gagal, kembalikan (cx_default, cy_default, None, None).

    Formula centroid:
      - Pisahkan wrist (landmark 0) dan 5 MCP joints (landmark 1,5,9,13,17)
      - Centroid = 30% wrist + 70% rata-rata MCP
      - Lebih robust untuk tangan miring karena tidak terlalu ditarik ke wrist
    """
    h, w      = img_bgr.shape[:2]
    cx, cy    = w // 2, h // 2
    landmarks = None
    hull      = None

    img_rgb  = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    result   = _landmarker.detect(mp_image)

    if result.hand_landmarks:
        lm = result.hand_landmarks[0]

        # Koordinat piksel semua PALM_LANDMARK_IDS
        xs = [int(lm[i].x * w) for i in PALM_LANDMARK_IDS]
        ys = [int(lm[i].y * h) for i in PALM_LANDMARK_IDS]

        # Pisahkan wrist (index 0) dan MCP joints (index 1-5)
        wrist_x = xs[0]
        wrist_y = ys[0]
        mcp_cx  = int(np.mean(xs[1:]))   # rata-rata 5 MCP joints
        mcp_cy  = int(np.mean(ys[1:]))

        # 30% wrist + 70% MCP → fokus ke tengah telapak
        cx = int(wrist_x * 0.3 + mcp_cx * 0.7)
        cy = int(wrist_y * 0.3 + mcp_cy * 0.7)

        # Clamp agar tidak keluar batas gambar
        cx = max(ROI_SIZE // 2, min(w - ROI_SIZE // 2, cx))
        cy = max(ROI_SIZE // 2, min(h - ROI_SIZE // 2, cy))

        landmarks = [(xs[i], ys[i]) for i in range(len(xs))]

        pts  = np.array([[xs[i], ys[i]] for i in range(len(xs))], dtype=np.int32)
        hull = cv2.convexHull(pts)

    return cx, cy, landmarks, hull


def _crop_roi(gray, cx, cy):
    """Center crop ROI_SIZE x ROI_SIZE dari centroid, dengan padding jika perlu."""
    h, w = gray.shape[:2]
    size = ROI_SIZE

    x1 = max(cx - size // 2, 0)
    y1 = max(cy - size // 2, 0)
    x2 = min(cx + size // 2, w)
    y2 = min(cy + size // 2, h)
    roi = gray[y1:y2, x1:x2]

    if roi.shape[0] < size or roi.shape[1] < size:
        pad = np.zeros((size, size), dtype=np.uint8)
        yo  = (size - roi.shape[0]) // 2
        xo  = (size - roi.shape[1]) // 2
        pad[yo:yo + roi.shape[0], xo:xo + roi.shape[1]] = roi
        roi = pad

    return roi, (x1, y1, x2, y2)


# ═══════════════════════════════════════════════════════════════════
# [A] UNTUK palmprint_api.py
#     Gantikan fungsi extract_roi() yang lama
# ═══════════════════════════════════════════════════════════════════

def extract_roi(img_bgr):
    """
    Deteksi ROI telapak tangan dengan MediaPipe HandLandmarker (v0.10+).

    Pipeline:
      1. MediaPipe -> 21 landmark tangan
      2. Ambil 6 landmark area palm (wrist + 5 MCP joints)
      3. Centroid = 30% wrist + 70% rata-rata MCP (robust untuk tangan miring)
      4. Center crop ROI_SIZE x ROI_SIZE
      5. Fallback ke center crop jika MediaPipe gagal

    Args:
        img_bgr: gambar BGR dari cv2.imread()

    Returns:
        roi (np.ndarray): grayscale ROI ukuran ROI_SIZE x ROI_SIZE
    """
    gray          = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    cx, cy, _, __ = _get_palm_centroid(img_bgr)
    roi, _        = _crop_roi(gray, cx, cy)
    return roi


# ═══════════════════════════════════════════════════════════════════
# [B] UNTUK palmprint_modeling.ipynb
#     Gantikan fungsi detect_palm_opencv() yang lama
#     Kompatibel penuh — debug_info punya key yang sama persis
# ═══════════════════════════════════════════════════════════════════

def detect_palm_opencv(img_bgr, debug=False):
    """
    Deteksi ROI telapak tangan dengan MediaPipe HandLandmarker (v0.10+).
    Drop-in replacement untuk detect_palm_opencv() di notebook.

    Mengembalikan (roi_gray, debug_info) persis seperti versi lama,
    sehingga semua kode visualisasi di notebook tidak perlu diubah.

    Args:
        img_bgr : gambar BGR dari cv2.imread()
        debug   : diabaikan (tetap ada untuk kompatibilitas signature)

    Returns:
        roi_gray (np.ndarray) : grayscale ROI ukuran ROI_SIZE x ROI_SIZE
        dbg      (dict)       : debug info kompatibel dengan notebook lama
    """
    h, w = img_bgr.shape[:2]
    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)

    cx, cy, landmarks, hull = _get_palm_centroid(img_bgr)
    roi, roi_rect           = _crop_roi(gray, cx, cy)

    # Buat mask visualisasi dari convex hull landmark
    mask_vis = np.zeros((h, w), dtype=np.uint8)
    if hull is not None:
        cv2.fillConvexPoly(mask_vis, hull, 255)

    dbg = {
        'mask_raw'  : mask_vis.copy(),
        'mask_clean': mask_vis.copy(),
        'fallback'  : landmarks is None,
        'contour'   : hull,
        'area'      : float(cv2.contourArea(hull)) if hull is not None else 0.0,
        'cx'        : cx,
        'cy'        : cy,
        'roi_rect'  : roi_rect,
        'landmarks' : landmarks,
    }

    return roi, dbg


# ═══════════════════════════════════════════════════════════════════
# QUICK TEST
# Jalankan: python roi_mediapipe.py path/to/gambar.jpg
# ═══════════════════════════════════════════════════════════════════

if __name__ == '__main__':
    import sys

    if len(sys.argv) < 2 or not os.path.exists(sys.argv[1]):
        print('Usage: python roi_mediapipe.py path/to/image.jpg')
        sys.exit(0)

    img = cv2.imread(sys.argv[1])
    print(f'Image shape : {img.shape}')

    roi = extract_roi(img)
    cv2.imwrite('test_roi_api.jpg', roi)
    print(f'extract_roi        -> shape={roi.shape}  saved: test_roi_api.jpg')

    roi2, dbg = detect_palm_opencv(img, debug=True)
    cv2.imwrite('test_roi_notebook.jpg', roi2)
    print(f'detect_palm_opencv -> shape={roi2.shape}  saved: test_roi_notebook.jpg')
    print(f'  centroid  : ({dbg["cx"]}, {dbg["cy"]})')
    print(f'  fallback  : {dbg["fallback"]}')
    print(f'  area      : {dbg["area"]:.0f} px2')
    print(f'  landmarks : {dbg["landmarks"]}')
