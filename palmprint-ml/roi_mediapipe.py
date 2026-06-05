# =============================================================================
# roi_mediapipe.py — ROI Extraction dengan MediaPipe HandLandmarker
# =============================================================================
# Versi  : Fase 1A (Alignment) + Dynamic ROI
# Requires: mediapipe >= 0.10  (API baru: HandLandmarker)
#
# Alur pipeline:
#   img_bgr
#     → _align_roi()           : rotasi agar tangan selalu vertikal
#     → MediaPipe detect        : deteksi 21 landmark tangan
#     → _compute_dynamic_roi_size() : ROI size proporsional ukuran tangan
#     → _get_palm_centroid()    : hitung centroid area telapak
#     → _crop_roi()             : crop + resize ke ROI_SIZE × ROI_SIZE
#
# API publik:
#   extract_roi(img_bgr)         → roi (grayscale, ROI_SIZE × ROI_SIZE)
#   detect_palm_opencv(img_bgr)  → roi, debug_dict
# =============================================================================

import os
import urllib.request

import cv2
import mediapipe as mp
import numpy as np
from mediapipe.tasks import python as mp_python
from mediapipe.tasks.python import vision as mp_vision
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
from mediapipe.tasks.python.vision.core.vision_task_running_mode import VisionTaskRunningMode


# =============================================================================
# KONSTANTA & INISIALISASI MODEL
# =============================================================================

# WAJIB SINKRON dengan Config.ROI_SIZE di notebook dan palmprint_api.py.
# Jika diubah → retrain model.
ROI_SIZE = 200

# Landmark index area telapak (bukan ujung jari):
#   0=wrist, 1=thumb_cmc, 5=index_mcp, 9=middle_mcp, 13=ring_mcp, 17=pinky_mcp
PALM_LANDMARK_IDS = [0, 1, 5, 9, 13, 17]

_MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'hand_landmarker.task')
_MODEL_URL  = (
    'https://storage.googleapis.com/mediapipe-models/'
    'hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task'
)

def _ensure_model():
    """Download model MediaPipe jika belum ada (±9 MB, sekali saja)."""
    if not os.path.exists(_MODEL_PATH):
        print(f'[roi_mediapipe] Downloading hand_landmarker.task (~9 MB)...')
        urllib.request.urlretrieve(_MODEL_URL, _MODEL_PATH)
        print(f'[roi_mediapipe] Model tersimpan: {_MODEL_PATH}')

_ensure_model()

_options = HandLandmarkerOptions(
    base_options                  = mp_python.BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode                  = VisionTaskRunningMode.IMAGE,
    num_hands                     = 1,
    min_hand_detection_confidence = 0.3,
    min_hand_presence_confidence  = 0.3,
    min_tracking_confidence       = 0.3,
)
_landmarker = HandLandmarker.create_from_options(_options)


# =============================================================================
# FASE 1A — ALIGNMENT
# =============================================================================

def _align_roi(img_bgr):
    """
    Rotasi gambar agar sumbu tangan selalu vertikal (tegak ke atas).

    Cara kerja:
      1. Deteksi landmark awal dengan MediaPipe
      2. Hitung sudut antara wrist (lm 0) dan middle MCP (lm 9)
         menggunakan arctan2(dx, -dy) — sudut dari sumbu vertikal
      3. Rotasi seluruh gambar sebesar -theta di sekitar titik tengah
      4. Tepi diisi dengan BORDER_REPLICATE (bukan hitam)

    Fallback:
      Jika MediaPipe tidak mendeteksi tangan, kembalikan gambar asli
      dengan angle=0.0 (pipeline tetap berjalan tanpa alignment).

    Args:
        img_bgr (np.ndarray): gambar BGR dari cv2.imread()

    Returns:
        img_aligned (np.ndarray): gambar BGR setelah dirotasi
        angle       (float)     : sudut rotasi dalam derajat (0.0 jika fallback)
    """
    h, w = img_bgr.shape[:2]

    img_rgb  = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    result   = _landmarker.detect(mp_image)

    if not result.hand_landmarks:
        return img_bgr, 0.0

    lm = result.hand_landmarks[0]

    # Koordinat piksel wrist (lm 0) dan middle MCP (lm 9)
    wrist_x = int(lm[0].x * w);  wrist_y = int(lm[0].y * h)
    mid_x   = int(lm[9].x * w);  mid_y   = int(lm[9].y * h)

    # Sudut dari sumbu vertikal ke atas
    # -dy karena sumbu Y gambar terbalik (atas = 0)
    dx    = mid_x - wrist_x
    dy    = mid_y - wrist_y
    angle = np.degrees(np.arctan2(dx, -dy))

    # Clamp ±45° — lebih dari itu kemungkinan deteksi salah (tangan terbalik)
    angle = float(np.clip(angle, -45, 45))

    M           = cv2.getRotationMatrix2D((w // 2, h // 2), angle, 1.0)
    img_aligned = cv2.warpAffine(
        img_bgr, M, (w, h),
        flags      = cv2.INTER_LINEAR,
        borderMode = cv2.BORDER_REPLICATE
    )
    return img_aligned, angle


# =============================================================================
# HELPER INTERNAL
# =============================================================================

def _compute_dynamic_roi_size(lm, w, h):
    """
    Hitung ukuran ROI secara dinamis berdasarkan jarak wrist → middle MCP.

    Tujuan:
      - Tangan dekat kamera (besar di frame) → ROI lebih besar
      - Tangan jauh kamera (kecil di frame)  → ROI lebih kecil
      - Hasil selalu di-clamp ke [ROI_SIZE_MIN, ROI_SIZE]

    Faktor 0.85 dipilih konservatif agar aman untuk dataset yang sudah
    zoom-in (TJI) maupun gambar dari kamera HP jarak normal.
    """
    ROI_SIZE_MIN = 80

    wrist_x = lm[0].x * w;  wrist_y = lm[0].y * h
    mid_x   = lm[9].x * w;  mid_y   = lm[9].y * h

    dist    = np.sqrt((mid_x - wrist_x)**2 + (mid_y - wrist_y)**2)
    roi_size = int(np.clip(dist * 0.85, ROI_SIZE_MIN, ROI_SIZE))
    return roi_size


def _get_palm_centroid(img_bgr):
    """
    Pipeline utama: alignment → deteksi landmark → hitung centroid + ROI size.

    Centroid dihitung sebagai:
      midpoint(wrist, rata-rata MCP) + offset 20% ke arah MCP
    sehingga crop terpusat di area telapak, bukan di pergelangan.

    Returns:
        cx, cy        : koordinat centroid (sudah di-clamp agar tidak out-of-bounds)
        landmarks     : list koordinat piksel 6 landmark telapak, atau None
        hull          : convex hull landmark (untuk visualisasi), atau None
        img_aligned   : gambar setelah alignment
        angle         : sudut rotasi dari _align_roi()
        dynamic_size  : ukuran ROI dinamis (piksel)
    """
    h, w = img_bgr.shape[:2]
    cx, cy       = w // 2, h // 2   # fallback ke tengah gambar
    landmarks    = None
    hull         = None
    dynamic_size = ROI_SIZE

    img_aligned, angle = _align_roi(img_bgr)

    img_rgb  = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    result   = _landmarker.detect(mp_image)

    if result.hand_landmarks:
        lm = result.hand_landmarks[0]

        dynamic_size = _compute_dynamic_roi_size(lm, w, h)

        xs = [int(lm[i].x * w) for i in PALM_LANDMARK_IDS]
        ys = [int(lm[i].y * h) for i in PALM_LANDMARK_IDS]

        wrist_x = xs[0];  wrist_y = ys[0]
        mcp_cx  = int(np.mean(xs[1:]))
        mcp_cy  = int(np.mean(ys[1:]))

        hand_len = np.sqrt((mcp_cx - wrist_x)**2 + (mcp_cy - wrist_y)**2)
        if hand_len > 0:
            dir_x = (mcp_cx - wrist_x) / hand_len
            dir_y = (mcp_cy - wrist_y) / hand_len
        else:
            dir_x, dir_y = 0.0, -1.0

        mid_x  = (wrist_x + mcp_cx) / 2
        mid_y  = (wrist_y + mcp_cy) / 2
        offset = hand_len * 0.20

        half = dynamic_size // 2
        cx   = int(np.clip(mid_x + dir_x * offset, half, w - half))
        cy   = int(np.clip(mid_y + dir_y * offset, half, h - half))

        landmarks = [(xs[i], ys[i]) for i in range(len(xs))]
        pts  = np.array([[xs[i], ys[i]] for i in range(len(xs))], dtype=np.int32)
        hull = cv2.convexHull(pts)

    return cx, cy, landmarks, hull, img_aligned, angle, dynamic_size


def _crop_roi(gray, cx, cy, size=None):
    """
    Center-crop dari centroid, lalu resize ke ROI_SIZE × ROI_SIZE.

    Resize memastikan dimensi output selalu konsisten untuk HOG-SGF downstream,
    meskipun ukuran crop dinamis berbeda-beda.

    Args:
        gray   : grayscale image (np.uint8)
        cx, cy : koordinat centroid
        size   : ukuran crop (piksel). None = pakai ROI_SIZE default.

    Returns:
        roi      (np.ndarray)  : grayscale ROI ukuran ROI_SIZE × ROI_SIZE
        roi_rect (tuple)       : (x1, y1, x2, y2) area crop di gambar asli
    """
    h, w = gray.shape[:2]
    size = size or ROI_SIZE
    half = size // 2

    x1 = max(cx - half, 0);  y1 = max(cy - half, 0)
    x2 = min(cx + half, w);  y2 = min(cy + half, h)
    roi = gray[y1:y2, x1:x2]

    # Padding jika crop menyentuh tepi gambar
    if roi.shape[0] < size or roi.shape[1] < size:
        pad = np.zeros((size, size), dtype=np.uint8)
        yo  = (size - roi.shape[0]) // 2
        xo  = (size - roi.shape[1]) // 2
        pad[yo:yo + roi.shape[0], xo:xo + roi.shape[1]] = roi
        roi = pad

    # Resize ke ROI_SIZE agar dimensi selalu konsisten
    if size != ROI_SIZE:
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE), interpolation=cv2.INTER_LINEAR)

    return roi, (x1, y1, x2, y2)


# =============================================================================
# API PUBLIK
# =============================================================================

def extract_roi(img_bgr):
    """
    Ekstrak ROI telapak tangan dari gambar BGR.

    Pipeline (Fase 1A + Dynamic ROI):
      1. Alignment  : rotasi gambar agar tangan vertikal
      2. Landmark   : deteksi 21 titik tangan dengan MediaPipe
      3. Dynamic ROI: ukuran crop proporsional jarak wrist → middle MCP
      4. Centroid   : midpoint(wrist, MCP) + offset 20% ke atas
      5. Crop + resize ke ROI_SIZE × ROI_SIZE
      6. Fallback ke center crop jika MediaPipe gagal

    Args:
        img_bgr (np.ndarray): gambar BGR (cv2.imread output)

    Returns:
        roi (np.ndarray): grayscale ROI ukuran ROI_SIZE × ROI_SIZE
    """
    cx, cy, _, __, img_aligned, _angle, dynamic_size = _get_palm_centroid(img_bgr)
    gray   = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, _ = _crop_roi(gray, cx, cy, size=dynamic_size)
    return roi


def detect_palm_opencv(img_bgr, debug=False):
    """
    Deteksi ROI telapak tangan + informasi debug.
    Drop-in replacement untuk detect_palm_opencv() di notebook.

    Args:
        img_bgr (np.ndarray): gambar BGR
        debug   (bool)      : tidak digunakan, dipertahankan untuk kompatibilitas

    Returns:
        roi_gray (np.ndarray): grayscale ROI ukuran ROI_SIZE × ROI_SIZE
        dbg      (dict)      : informasi debug — key:

            Backward-compatible:
              'mask_raw'         : mask visualisasi convex hull
              'mask_clean'       : sama dengan mask_raw
              'fallback'         : True jika MediaPipe gagal (pakai center crop)
              'contour'          : convex hull landmark (np.ndarray atau None)
              'area'             : luas convex hull (float)
              'cx', 'cy'         : koordinat centroid
              'roi_rect'         : (x1, y1, x2, y2) area crop
              'landmarks'        : list koordinat 6 landmark telapak, atau None

            Fase 1A + Dynamic ROI:
              'angle'            : sudut rotasi alignment (derajat)
              'img_aligned'      : gambar setelah alignment (BGR)
              'dynamic_roi_size' : ukuran crop dinamis (piksel)
    """
    h, w = img_bgr.shape[:2]

    cx, cy, landmarks, hull, img_aligned, angle, dynamic_size = _get_palm_centroid(img_bgr)

    gray          = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, roi_rect = _crop_roi(gray, cx, cy, size=dynamic_size)

    mask_vis = np.zeros((h, w), dtype=np.uint8)
    if hull is not None:
        cv2.fillConvexPoly(mask_vis, hull, 255)

    dbg = {
        # Backward-compatible
        'mask_raw'        : mask_vis.copy(),
        'mask_clean'      : mask_vis.copy(),
        'fallback'        : landmarks is None,
        'contour'         : hull,
        'area'            : float(cv2.contourArea(hull)) if hull is not None else 0.0,
        'cx'              : cx,
        'cy'              : cy,
        'roi_rect'        : roi_rect,
        'landmarks'       : landmarks,
        # Fase 1A + Dynamic ROI
        'angle'           : angle,
        'img_aligned'     : img_aligned,
        'dynamic_roi_size': dynamic_size,
    }

    return roi, dbg


# =============================================================================
# QUICK TEST — python roi_mediapipe.py path/to/gambar.jpg
# =============================================================================

if __name__ == '__main__':
    import sys
    import matplotlib.pyplot as plt

    if len(sys.argv) < 2 or not os.path.exists(sys.argv[1]):
        print('Usage: python roi_mediapipe.py path/to/image.jpg')
        sys.exit(0)

    img = cv2.imread(sys.argv[1])
    print(f'Image shape  : {img.shape}')

    roi, dbg = detect_palm_opencv(img)

    # ── Simpan output ──
    cv2.imwrite('test_roi.jpg',         roi)
    cv2.imwrite('test_roi_aligned.jpg', dbg['img_aligned'])
    print(f'ROI shape    : {roi.shape}')
    print(f'Angle        : {dbg["angle"]:.2f}°')
    print(f'Centroid     : ({dbg["cx"]}, {dbg["cy"]})')
    print(f'ROI rect     : {dbg["roi_rect"]}')
    print(f'Dynamic size : {dbg["dynamic_roi_size"]} px')
    print(f'Palm area    : {dbg["area"]:.0f} px²')
    print(f'Fallback     : {dbg["fallback"]}')
    print(f'Saved        : test_roi.jpg, test_roi_aligned.jpg')

    # ── Visualisasi ──
    img_ann = dbg['img_aligned'].copy()
    rx, ry, rx2, ry2 = dbg['roi_rect']
    cv2.rectangle(img_ann, (rx, ry), (rx2, ry2), (0, 165, 255), 3)
    cv2.circle(img_ann, (dbg['cx'], dbg['cy']), 8, (0, 255, 0), -1)
    if dbg['contour'] is not None:
        cv2.drawContours(img_ann, [dbg['contour']], -1, (0, 255, 0), 2)

    fig, axes = plt.subplots(1, 4, figsize=(18, 4))
    fig.suptitle(f'ROI Extraction — {os.path.basename(sys.argv[1])}',
                 fontsize=12, fontweight='bold')

    axes[0].imshow(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
    axes[0].set_title('1. Input', fontweight='bold'); axes[0].axis('off')

    axes[1].imshow(cv2.cvtColor(dbg['img_aligned'], cv2.COLOR_BGR2RGB))
    axes[1].set_title(f'2. Aligned\n({dbg["angle"]:.1f}°)', fontweight='bold')
    axes[1].axis('off')

    axes[2].imshow(cv2.cvtColor(img_ann, cv2.COLOR_BGR2RGB))
    axes[2].set_title(f'3. Landmark + ROI Box\n(dynamic={dbg["dynamic_roi_size"]}px)',
                      fontweight='bold'); axes[2].axis('off')

    axes[3].imshow(roi, cmap='gray')
    axes[3].set_title(f'4. ROI Crop\n{ROI_SIZE}×{ROI_SIZE}px', fontweight='bold')
    axes[3].axis('off')

    plt.tight_layout()
    plt.savefig('test_roi_vis.jpg', dpi=100, bbox_inches='tight')
    plt.show()
    print('Saved        : test_roi_vis.jpg')