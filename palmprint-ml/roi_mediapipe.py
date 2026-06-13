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

# WAJIB SINKRON dengan Config.ROI_SIZE di notebook dan extractor.py.
# Jika diubah → retrain model.
ROI_SIZE = 200
# Landmark index area telapak (bukan ujung jari):
#   0=wrist, 1=thumb_cmc, 5=index_mcp, 9=middle_mcp, 13=ring_mcp, 17=pinky_mcp
PALM_LANDMARK_IDS = [0, 1, 5, 9, 13, 17]

# Batas rotasi alignment — lebih dari ini kemungkinan deteksi salah atau
# user memang perlu meluruskan tangan
ALIGN_ANGLE_MAX = 100.0

# Factor dynamic ROI: roi_size = dist(wrist→middle_mcp) * factor
# 0.70 dipilih konservatif agar aman untuk dataset zoom-in (TJI)
# maupun gambar dari kamera HP jarak normal (~20cm)
DYNAMIC_ROI_FACTOR = 0.70
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

# Confidence 0.6 — lebih ketat dari 0.3 sebelumnya.
# Untuk IMAGE mode tidak ada trade-off dengan tracking speed,
# jadi lebih baik tolak deteksi ambigu daripada hasilkan landmark salah.
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
    Wrapper tipis MediaPipe detect — konversi BGR→RGB, bungkus mp.Image,
    kembalikan result. Dipakai di dua tempat sehingga tidak duplikasi kode.
    """
    img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    return _landmarker.detect(mp_image)


def _compute_angle(lm, w: int, h: int) -> float:
    """
    Hitung sudut rotasi dari landmark hasil detect.

    Sudut diukur dari sumbu vertikal ke atas menggunakan arctan2(dx, -dy):
      - dx = middle_mcp_x - wrist_x
      - dy = middle_mcp_y - wrist_y  (-dy karena Y gambar terbalik)

    Return: float dalam derajat, sudah di-clamp ke [-ALIGN_ANGLE_MAX, +ALIGN_ANGLE_MAX]
    """
    wrist_x = int(lm[0].x * w)
    wrist_y = int(lm[0].y * h)
    mid_x = int(lm[9].x * w)
    mid_y = int(lm[9].y * h)

    dx = mid_x - wrist_x
    dy = mid_y - wrist_y
    angle = np.degrees(np.arctan2(dx, -dy))
    return float(np.clip(angle, -ALIGN_ANGLE_MAX, ALIGN_ANGLE_MAX))


def _rotate_image(img_bgr: np.ndarray, angle: float) -> np.ndarray:
    """
    Rotasi dengan canvas diperbesar — tangan tidak terpotong saat rotasi besar.
    """
    h, w = img_bgr.shape[:2]

    # Hitung ukuran canvas yang cukup untuk menampung rotasi apapun
    diag = int(np.ceil(np.sqrt(h**2 + w**2)))
    pad_h = (diag - h) // 2
    pad_w = (diag - w) // 2

    # Padding dulu sebelum rotasi
    img_padded = cv2.copyMakeBorder(
        img_bgr,
        top=pad_h,
        bottom=pad_h,
        left=pad_w,
        right=pad_w,
        borderType=cv2.BORDER_REPLICATE,
    )

    # Rotasi di tengah canvas yang lebih besar
    hp, wp = img_padded.shape[:2]
    M = cv2.getRotationMatrix2D((wp // 2, hp // 2), angle, 1.0)
    rotated = cv2.warpAffine(
        img_padded,
        M,
        (wp, hp),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_REPLICATE,
    )

    # Crop balik ke ukuran semula + sedikit margin
    rotated = rotated[pad_h : pad_h + h, pad_w : pad_w + w]
    return rotated


def _compute_dynamic_roi_size(lm, w: int, h: int) -> int:
    """
    Hitung ukuran ROI secara dinamis berdasarkan jarak wrist → middle MCP.

    Tujuan:
      - Tangan dekat kamera (besar di frame) → ROI lebih besar
      - Tangan jauh kamera (kecil di frame)  → ROI lebih kecil
      - Hasil di-clamp ke [ROI_SIZE_MIN, ROI_SIZE]

    Factor DYNAMIC_ROI_FACTOR=0.70 dipilih konservatif agar crop tidak
    terlalu lebar dan memasukkan area jari ke dalam ROI.
    """
    wrist_x = lm[0].x * w
    wrist_y = lm[0].y * h
    mid_x = lm[9].x * w
    mid_y = lm[9].y * h

    dist = np.sqrt((mid_x - wrist_x) ** 2 + (mid_y - wrist_y) ** 2)
    roi_size = int(np.clip(dist * DYNAMIC_ROI_FACTOR, ROI_SIZE_MIN, ROI_SIZE))
    return roi_size


def _compute_palm_center(lm, w: int, h: int) -> tuple[int, int]:

    # index MCP
    Ax = lm[5].x * w
    Ay = lm[5].y * h

    # pinky MCP
    Bx = lm[17].x * w
    By = lm[17].y * h

    # wrist
    Wx = lm[0].x * w
    Wy = lm[0].y * h

    # midpoint MCP
    mid_x = (Ax + Bx) / 2.0
    mid_y = (Ay + By) / 2.0

    # vector MCP
    vx = Bx - Ax
    vy = By - Ay

    palm_width = np.sqrt(vx * vx + vy * vy)

    if palm_width < 1:
        return int(mid_x), int(mid_y)

    # normal vector
    nx = -vy / palm_width
    ny = vx / palm_width

    # midpoint -> wrist
    wx = Wx - mid_x
    wy = Wy - mid_y

    dot = nx * wx + ny * wy

    if dot < 0:
        nx = -nx
        ny = -ny

    offset = palm_width * 0.40
    cx = mid_x + nx * offset
    cy = mid_y + ny * offset

    return int(cx), int(cy)


def _crop_roi(
    gray: np.ndarray,
    cx: int,
    cy: int,
    size: int | None = None,
) -> tuple[np.ndarray, tuple[int, int, int, int]]:
    """
    Center-crop dari centroid, lalu resize ke ROI_SIZE × ROI_SIZE.

    Jika crop menyentuh tepi gambar, padding dengan BORDER_REPLICATE
    (bukan nol/hitam) agar tidak ada transisi tajam yang direspons Gabor.

    Args:
        gray   : grayscale image (np.uint8)
        cx, cy : koordinat centroid
        size   : ukuran crop. None = pakai ROI_SIZE default.

    Returns:
        roi      : grayscale ROI ukuran ROI_SIZE × ROI_SIZE
        roi_rect : (x1, y1, x2, y2) area crop di gambar asli
    """
    h, w = gray.shape[:2]
    size = size or ROI_SIZE
    half = size // 2

    x1 = max(cx - half, 0)
    y1 = max(cy - half, 0)
    x2 = min(cx + half, w)
    y2 = min(cy + half, h)
    roi = gray[y1:y2, x1:x2]

    # Padding jika crop menyentuh tepi — pakai REPLICATE bukan nol
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

    # Resize ke ROI_SIZE agar dimensi selalu konsisten untuk HOG-SGF downstream
    if size != ROI_SIZE:
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE), interpolation=cv2.INTER_AREA)

    return roi, (x1, y1, x2, y2)


# =============================================================================
# FUNGSI INTI — ALIGNMENT + CENTROID
# =============================================================================


def _get_palm_centroid(img_bgr: np.ndarray):
    """
    Pipeline utama: detect #1 → alignment → detect #2 → centroid + ROI size.

    Dua kali detect diperlukan:
      - Detect #1 pada gambar asli untuk mendapatkan angle alignment
      - Detect #2 pada gambar setelah rotasi untuk mendapatkan landmark
        yang akurat di koordinat gambar yang sudah lurus

    Jika detect #1 gagal → kembalikan gambar asli dengan fallback=True.
    Jika detect #2 gagal (jarang, tapi mungkin setelah rotasi agresif)
    → kembalikan center crop dengan fallback=True.

    Returns:
        cx, cy        : koordinat centroid (int)
        landmarks     : list (x, y) dari 6 landmark palm, atau None
        hull          : convex hull landmark (untuk visualisasi), atau None
        img_aligned   : gambar setelah alignment
        angle         : sudut rotasi (float, derajat)
        dynamic_size  : ukuran ROI dinamis (int, piksel)
        fallback      : True jika MediaPipe gagal di salah satu detect
    """
    h, w = img_bgr.shape[:2]
    cx, cy = w // 2, h // 2
    angle = 0.0
    dynamic_size = ROI_SIZE

    # ── Detect #1: gambar asli → angle ──
    result1 = _detect(img_bgr)

    if not result1.hand_landmarks:
        # Tidak ada tangan sama sekali → fallback
        return cx, cy, None, None, img_bgr, angle, dynamic_size, True

    lm1 = result1.hand_landmarks[0]
    angle = _compute_angle(lm1, w, h)

    # ── Alignment ──
    img_aligned = _rotate_image(img_bgr, angle)

    # ── Detect #2: gambar aligned → centroid ──
    result2 = _detect(img_aligned)

    if not result2.hand_landmarks:
        # Pakai landmark dari detect #1 tapi di gambar aligned
        # Re-project landmark lm1 ke koordinat gambar aligned
        dynamic_size = _compute_dynamic_roi_size(lm1, w, h)
        palm_cx, palm_cy = _compute_palm_center(lm1, w, h)
        
        half = dynamic_size // 2
        cx = int(np.clip(palm_cx, half, w - half))
        cy = int(np.clip(palm_cy, half, h - half))
        
        xs = [int(lm1[i].x * w) for i in PALM_LANDMARK_IDS]
        ys = [int(lm1[i].y * h) for i in PALM_LANDMARK_IDS]
        landmarks = list(zip(xs, ys))
        pts = np.array([[xs[i], ys[i]] for i in range(len(xs))], dtype=np.int32)
        hull = cv2.convexHull(pts)
        
        # fallback=False karena kita masih punya landmark valid
        return cx, cy, landmarks, hull, img_aligned, angle, dynamic_size, False

    lm2 = result2.hand_landmarks[0]

    dynamic_size = _compute_dynamic_roi_size(lm2, w, h)
    palm_cx, palm_cy = _compute_palm_center(lm2, w, h)

    half = dynamic_size // 2
    cx = int(np.clip(palm_cx, half, w - half))
    cy = int(np.clip(palm_cy, half, h - half))

    xs = [int(lm2[i].x * w) for i in PALM_LANDMARK_IDS]
    ys = [int(lm2[i].y * h) for i in PALM_LANDMARK_IDS]
    landmarks = list(zip(xs, ys))
    pts = np.array([[xs[i], ys[i]] for i in range(len(xs))], dtype=np.int32)
    hull = cv2.convexHull(pts)

    return cx, cy, landmarks, hull, img_aligned, angle, dynamic_size, False


# =============================================================================
# API PUBLIK
# =============================================================================


def extract_roi(img_bgr: np.ndarray) -> np.ndarray:
    """
    Ekstrak ROI telapak tangan dari gambar BGR.

    Pipeline (Fase 1B):
      1. Detect #1 pada gambar asli → angle alignment
      2. Rotasi gambar agar tangan vertikal
      3. Detect #2 pada gambar aligned → centroid + dynamic ROI size
      4. Centroid = mean(6 landmark palm) — lebih stabil dari offset wrist
      5. Crop dengan BORDER_REPLICATE jika menyentuh tepi
      6. Resize ke ROI_SIZE × ROI_SIZE

    Args:
        img_bgr: gambar BGR (cv2.imread output)

    Returns:
        roi: grayscale ROI ukuran ROI_SIZE × ROI_SIZE
    """
    cx, cy, _, __, img_aligned, _angle, dynamic_size, _fallback = _get_palm_centroid(
        img_bgr
    )
    gray = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, _ = _crop_roi(gray, cx, cy, size=dynamic_size)
    return roi


def detect_palm_opencv(img_bgr: np.ndarray, debug: bool = False):
    """
    Deteksi ROI telapak tangan + informasi debug.
    Drop-in replacement untuk detect_palm_opencv() di notebook.

    Args:
        img_bgr : gambar BGR
        debug   : tidak digunakan, dipertahankan untuk kompatibilitas

    Returns:
        roi_gray : grayscale ROI ukuran ROI_SIZE × ROI_SIZE
        dbg      : dict dengan key:

            Backward-compatible:
              'mask_raw'         : mask visualisasi convex hull
              'mask_clean'       : sama dengan mask_raw
              'fallback'         : True jika MediaPipe gagal
              'contour'          : convex hull landmark (np.ndarray atau None)
              'area'             : luas convex hull (float)
              'cx', 'cy'         : koordinat centroid
              'roi_rect'         : (x1, y1, x2, y2) area crop
              'landmarks'        : list (x,y) 6 landmark palm, atau None

            Fase 1B:
              'angle'            : sudut rotasi alignment (derajat)
              'img_aligned'      : gambar setelah alignment (BGR)
              'dynamic_roi_size' : ukuran crop dinamis (piksel)
    """
    h, w = img_bgr.shape[:2]

    cx, cy, landmarks, hull, img_aligned, angle, dynamic_size, fallback = (
        _get_palm_centroid(img_bgr)
    )

    gray = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, roi_rect = _crop_roi(gray, cx, cy, size=dynamic_size)

    mask_vis = np.zeros((h, w), dtype=np.uint8)
    if hull is not None:
        cv2.fillConvexPoly(mask_vis, hull, 255)

    dbg = {
        # Backward-compatible
        "mask_raw": mask_vis.copy(),
        "mask_clean": mask_vis.copy(),
        "fallback": fallback,
        "contour": hull,
        "area": float(cv2.contourArea(hull)) if hull is not None else 0.0,
        "cx": cx,
        "cy": cy,
        "roi_rect": roi_rect,
        "landmarks": landmarks,
        # Fase 1B
        "angle": angle,
        "img_aligned": img_aligned,
        "dynamic_roi_size": dynamic_size,
    }

    return roi, dbg


# =============================================================================
# QUICK TEST — python roi_mediapipe.py path/to/gambar.jpg
# =============================================================================

if __name__ == "__main__":
    import sys
    import matplotlib.pyplot as plt

    if len(sys.argv) < 2 or not os.path.exists(sys.argv[1]):
        print("Usage: python roi_mediapipe.py path/to/image.jpg")
        sys.exit(0)

    img = cv2.imread(sys.argv[1])
    print(f"Image shape  : {img.shape}")

    roi, dbg = detect_palm_opencv(img)

    cv2.imwrite("test_roi.jpg", roi)
    cv2.imwrite("test_roi_aligned.jpg", dbg["img_aligned"])
    print(f"ROI shape    : {roi.shape}")
    print(f'Angle        : {dbg["angle"]:.2f}°')
    print(f'Centroid     : ({dbg["cx"]}, {dbg["cy"]})')
    print(f'ROI rect     : {dbg["roi_rect"]}')
    print(f'Dynamic size : {dbg["dynamic_roi_size"]} px')
    print(f'Palm area    : {dbg["area"]:.0f} px²')
    print(f'Fallback     : {dbg["fallback"]}')
    print("Saved        : test_roi.jpg, test_roi_aligned.jpg")

    img_ann = dbg["img_aligned"].copy()

    rx, ry, rx2, ry2 = dbg["roi_rect"]
    cv2.rectangle(img_ann, (rx, ry), (rx2, ry2), (0, 165, 255), 3)
    cv2.circle(img_ann, (dbg["cx"], dbg["cy"]), 8, (0, 255, 0), -1)
    if dbg["contour"] is not None:
        cv2.drawContours(img_ann, [dbg["contour"]], -1, (0, 255, 0), 2)
    if dbg["landmarks"] is not None:
        for lx, ly in dbg["landmarks"]:
            cv2.circle(img_ann, (lx, ly), 5, (0, 0, 255), -1)

    fig, axes = plt.subplots(1, 4, figsize=(18, 4))
    fig.suptitle(
        f"ROI Extraction Fase 1B — {os.path.basename(sys.argv[1])}",
        fontsize=12,
        fontweight="bold",
    )
    axes[0].imshow(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
    axes[0].set_title("1. Input", fontweight="bold")
    axes[0].axis("off")
    axes[1].imshow(cv2.cvtColor(dbg["img_aligned"], cv2.COLOR_BGR2RGB))
    axes[1].set_title(f'2. Aligned ({dbg["angle"]:.1f}°)', fontweight="bold")
    axes[1].axis("off")
    axes[2].imshow(cv2.cvtColor(img_ann, cv2.COLOR_BGR2RGB))
    axes[2].set_title(
        f'3. Centroid + ROI Box\n(dynamic={dbg["dynamic_roi_size"]}px)',
        fontweight="bold",
    )
    axes[2].axis("off")
    axes[3].imshow(roi, cmap="gray")
    axes[3].set_title(f"4. ROI Crop\n{ROI_SIZE}×{ROI_SIZE}px", fontweight="bold")
    axes[3].axis("off")

    plt.tight_layout()
    plt.savefig("test_roi_vis.jpg", dpi=100, bbox_inches="tight")
    plt.show()
    print("Saved        : test_roi_vis.jpg")
