"""
roi_mediapipe.py  —  Fase 1A: ROI Alignment
=============================================
Perubahan dari versi sebelumnya:
  - Tambah fungsi _align_roi()   : rotasi gambar agar sumbu tangan selalu vertikal
  - Modif _get_palm_centroid()   : alignment dilakukan SEBELUM deteksi landmark & crop
  - extract_roi() dan detect_palm_opencv() TIDAK BERUBAH (otomatis ikut terupdate)

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

# Index ke-3 dalam PALM_LANDMARK_IDS = landmark 9 (middle finger MCP)
# Dipakai sebagai ujung sumbu untuk menghitung sudut kemiringan tangan
_MIDDLE_MCP_IDX = 3

ROI_SIZE = 200


# ═══════════════════════════════════════════════════════════════════
# [BARU - FASE 1A] FUNGSI ALIGNMENT
# ═══════════════════════════════════════════════════════════════════

def _align_roi(img_bgr):
    """
    Rotasi gambar agar sumbu tangan selalu vertikal (tegak lurus ke atas).

    Cara kerja:
      1. Jalankan MediaPipe sekali untuk deteksi awal landmark
      2. Hitung sudut antara wrist (lm 0) dan middle MCP (lm 9)
         menggunakan arctan2(dx, dy) — sudut dari sumbu vertikal
      3. Rotasi seluruh gambar sebesar -theta agar sumbu tangan = vertikal
      4. Kembalikan gambar yang sudah dirotasi

    Fallback: jika MediaPipe tidak mendeteksi tangan, kembalikan gambar asli
    (proses tetap berjalan, hanya tidak ada alignment)

    Args:
        img_bgr: gambar BGR dari cv2.imread()

    Returns:
        img_aligned (np.ndarray): gambar BGR setelah dirotasi
        angle (float): sudut rotasi dalam derajat (0.0 jika fallback)
    """
    h, w = img_bgr.shape[:2]

    # Deteksi awal untuk mendapat landmark
    img_rgb  = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    result   = _landmarker.detect(mp_image)

    # Fallback: tidak ada tangan terdeteksi
    if not result.hand_landmarks:
        return img_bgr, 0.0

    lm = result.hand_landmarks[0]

    # Koordinat piksel wrist (lm 0) dan middle MCP (lm 9)
    wrist_x  = int(lm[0].x * w)
    wrist_y  = int(lm[0].y * h)
    mid_x    = int(lm[9].x * w)   # middle finger MCP
    mid_y    = int(lm[9].y * h)

    # Hitung sudut kemiringan terhadap sumbu vertikal
    # dx, dy = selisih koordinat dari wrist ke middle MCP
    dx = mid_x - wrist_x
    dy = mid_y - wrist_y  # dy negatif jika middle MCP di atas wrist (normal)

    # arctan2(dx, -dy): sudut dari vertikal ke atas
    # Negatif dy karena sumbu Y gambar terbalik (atas = 0)
    angle = np.degrees(np.arctan2(dx, -dy))

    # Clamp sudut: hanya koreksi ±45 derajat
    # Lebih dari itu kemungkinan deteksi salah (tangan terbalik dll)
    angle = np.clip(angle, -45, 45)

    # Rotasi gambar di sekitar titik tengah
    center = (w // 2, h // 2)
    M      = cv2.getRotationMatrix2D(center, angle, 1.0)

    # BORDER_REPLICATE: isi tepi dengan piksel terdekat (menghindari border hitam)
    img_aligned = cv2.warpAffine(
        img_bgr, M, (w, h),
        flags       = cv2.INTER_LINEAR,
        borderMode  = cv2.BORDER_REPLICATE
    )

    return img_aligned, angle


# ═══════════════════════════════════════════════════════════════════
# FUNGSI HELPER INTERNAL (dimodifikasi untuk alignment)
# ═══════════════════════════════════════════════════════════════════

def _compute_dynamic_roi_size(lm, w, h):
    """
    Hitung ukuran ROI secara dinamis berdasarkan jarak antar landmark.

    - Tangan dekat (dataset TJI) → dist besar → tapi di-clamp agar tidak overflow
    - Tangan jauh (kamera HP)   → dist kecil → crop tight ke telapak

    Faktor 1.1 (turun dari 1.4):
      Lebih aman untuk dataset TJI yang sudah zoom-in.
      Untuk kamera HP, dynamic ROI tetap lebih besar dari fixed 200px
      sehingga tetap tight ke telapak.
    """
    ROI_SIZE_MIN = 100
    ROI_SIZE_MAX = 320  # turun dari 400

    wrist_x  = lm[0].x * w
    wrist_y  = lm[0].y * h
    mid_x    = lm[9].x * w
    mid_y    = lm[9].y * h

    dist_wrist_to_mid = np.sqrt((mid_x - wrist_x)**2 + (mid_y - wrist_y)**2)

    # Faktor 1.1 — lebih konservatif, aman untuk kedua kondisi
    roi_size = int(dist_wrist_to_mid * 1.1)
    roi_size = int(np.clip(roi_size, ROI_SIZE_MIN, ROI_SIZE_MAX))

    return roi_size


def _get_palm_centroid(img_bgr):
    """
    [DIMODIFIKASI - FASE 1A + Dynamic ROI]
    Jalankan alignment terlebih dahulu, lalu deteksi landmark pada gambar
    yang sudah di-align. ROI size dihitung dinamis berdasarkan ukuran tangan.

    Pipeline baru:
      img_bgr → _align_roi() → img_aligned → MediaPipe detect
             → dynamic ROI size → centroid → return

    Perubahan:
      - Alignment sebelum deteksi (Fase 1A)
      - ROI size dinamis berdasarkan jarak wrist→middle MCP
      - Return tambahan: img_aligned, angle, dynamic_roi_size
    """
    h, w = img_bgr.shape[:2]
    cx, cy       = w // 2, h // 2
    landmarks    = None
    hull         = None
    dynamic_size = ROI_SIZE  # fallback ke default

    # ── FASE 1A: Alignment sebelum deteksi ──
    img_aligned, angle = _align_roi(img_bgr)

    # Deteksi landmark pada gambar yang sudah di-align
    img_rgb  = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    result   = _landmarker.detect(mp_image)

    if result.hand_landmarks:
        lm = result.hand_landmarks[0]

        # Hitung ROI size dinamis dari ukuran tangan di frame
        dynamic_size = _compute_dynamic_roi_size(lm, w, h)

        xs = [int(lm[i].x * w) for i in PALM_LANDMARK_IDS]
        ys = [int(lm[i].y * h) for i in PALM_LANDMARK_IDS]

        wrist_x = xs[0]
        wrist_y = ys[0]
        mcp_cx  = int(np.mean(xs[1:]))
        mcp_cy  = int(np.mean(ys[1:]))

        # Panjang tangan = jarak wrist ke rata-rata MCP (dalam piksel)
        hand_len = np.sqrt((mcp_cx - wrist_x)**2 + (mcp_cy - wrist_y)**2)

        # Arah unit vector dari WRIST ke MCP (ke atas, arah jari)
        if hand_len > 0:
            dir_x = (mcp_cx - wrist_x) / hand_len
            dir_y = (mcp_cy - wrist_y) / hand_len
        else:
            dir_x, dir_y = 0, -1

        # Mulai dari midpoint, geser 20% ke arah MCP (ke atas menuju telapak tengah)
        mid_x = int((wrist_x + mcp_cx) / 2)
        mid_y = int((wrist_y + mcp_cy) / 2)
        offset = hand_len * 0.20

        cx = int(mid_x + dir_x * offset)
        cy = int(mid_y + dir_y * offset)

        # Clamp centroid agar crop tidak keluar batas gambar
        half = dynamic_size // 2
        cx = max(half, min(w - half, cx))
        cy = max(half, min(h - half, cy))

        landmarks = [(xs[i], ys[i]) for i in range(len(xs))]
        pts  = np.array([[xs[i], ys[i]] for i in range(len(xs))], dtype=np.int32)
        hull = cv2.convexHull(pts)

    return cx, cy, landmarks, hull, img_aligned, angle, dynamic_size


def _crop_roi(gray, cx, cy, size=None):
    """
    Center crop dari centroid dengan ukuran dinamis.
    Hasil selalu di-resize ke ROI_SIZE x ROI_SIZE agar konsisten
    untuk ekstraksi fitur HOG-SGF downstream.

    Args:
        gray : grayscale image
        cx, cy: koordinat centroid
        size  : ukuran crop (piksel). None = pakai ROI_SIZE default.

    Returns:
        roi      : grayscale ROI ukuran ROI_SIZE x ROI_SIZE (selalu konsisten)
        roi_rect : (x1, y1, x2, y2) area crop di gambar asli
    """
    h, w    = gray.shape[:2]
    size    = size or ROI_SIZE
    half    = size // 2

    x1 = max(cx - half, 0)
    y1 = max(cy - half, 0)
    x2 = min(cx + half, w)
    y2 = min(cy + half, h)
    roi = gray[y1:y2, x1:x2]

    # Padding jika crop terlalu kecil (terjadi di tepi gambar)
    if roi.shape[0] < size or roi.shape[1] < size:
        pad = np.zeros((size, size), dtype=np.uint8)
        yo  = (size - roi.shape[0]) // 2
        xo  = (size - roi.shape[1]) // 2
        pad[yo:yo + roi.shape[0], xo:xo + roi.shape[1]] = roi
        roi = pad

    # Resize ke ROI_SIZE agar dimensi selalu konsisten untuk HOG-SGF
    if size != ROI_SIZE:
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE), interpolation=cv2.INTER_LINEAR)

    return roi, (x1, y1, x2, y2)


# ═══════════════════════════════════════════════════════════════════
# [A] UNTUK palmprint_api.py  —  TIDAK BERUBAH
#     Tapi sekarang internaly pakai alignment
# ═══════════════════════════════════════════════════════════════════

def extract_roi(img_bgr):
    """
    Deteksi ROI telapak tangan dengan MediaPipe HandLandmarker (v0.10+).

    Pipeline (Fase 1A + Dynamic ROI):
      1. Alignment: rotasi gambar agar tangan selalu vertikal
      2. MediaPipe → 21 landmark tangan
      3. Dynamic ROI size: proporsional terhadap jarak wrist→middle MCP
      4. Centroid = 30% wrist + 70% rata-rata MCP
      5. Crop dynamic_size × dynamic_size → resize ke ROI_SIZE × ROI_SIZE
      6. Fallback ke center crop jika MediaPipe gagal

    Returns:
        roi (np.ndarray): grayscale ROI ukuran ROI_SIZE x ROI_SIZE
    """
    cx, cy, _, __, img_aligned, _angle, dynamic_size = _get_palm_centroid(img_bgr)
    gray   = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, _ = _crop_roi(gray, cx, cy, size=dynamic_size)
    return roi


def detect_palm_opencv(img_bgr, debug=False):
    """
    Deteksi ROI telapak tangan dengan MediaPipe HandLandmarker (v0.10+).
    Drop-in replacement untuk detect_palm_opencv() di notebook.

    Fase 1A + Dynamic ROI update:
      - ROI size dinamis berdasarkan ukuran tangan di frame
      - debug_info tambah key: 'angle', 'img_aligned', 'dynamic_roi_size'
      - Semua key lama tetap ada (backward compatible)

    Returns:
        roi_gray (np.ndarray) : grayscale ROI ukuran ROI_SIZE x ROI_SIZE
        dbg      (dict)       : debug info lengkap
    """
    h, w = img_bgr.shape[:2]

    cx, cy, landmarks, hull, img_aligned, angle, dynamic_size = _get_palm_centroid(img_bgr)

    gray          = cv2.cvtColor(img_aligned, cv2.COLOR_BGR2GRAY)
    roi, roi_rect = _crop_roi(gray, cx, cy, size=dynamic_size)

    mask_vis = np.zeros((h, w), dtype=np.uint8)
    if hull is not None:
        cv2.fillConvexPoly(mask_vis, hull, 255)

    dbg = {
        # ── Key lama (backward compatible) ──
        'mask_raw'        : mask_vis.copy(),
        'mask_clean'      : mask_vis.copy(),
        'fallback'        : landmarks is None,
        'contour'         : hull,
        'area'            : float(cv2.contourArea(hull)) if hull is not None else 0.0,
        'cx'              : cx,
        'cy'              : cy,
        'roi_rect'        : roi_rect,
        'landmarks'       : landmarks,
        # ── Key baru (Fase 1A + Dynamic ROI) ──
        'angle'           : angle,
        'img_aligned'     : img_aligned,
        'dynamic_roi_size': dynamic_size,
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
    cv2.imwrite('test_roi_aligned.jpg', dbg['img_aligned'])
    print(f'detect_palm_opencv -> shape={roi2.shape}  saved: test_roi_notebook.jpg')
    print(f'  centroid    : ({dbg["cx"]}, {dbg["cy"]})')
    print(f'  angle       : {dbg["angle"]:.2f} derajat')
    print(f'  fallback    : {dbg["fallback"]}')
    print(f'  area        : {dbg["area"]:.0f} px2')
    print(f'  landmarks   : {dbg["landmarks"]}')
    print(f'  img_aligned : saved: test_roi_aligned.jpg')