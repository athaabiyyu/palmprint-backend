"""
fase1b_masking_patch.py — Palm Masking sebelum Gabor
=====================================================
Tambahan untuk Fase 1B: mask area luar telapak sebelum enhancement.
Ini menghilangkan noise dari background yang ikut masuk ROI.

Cara apply:
  1. Di roi_mediapipe.py  — tambahkan return hull_mask di detect_palm_opencv()
  2. Di palmprint_api.py  — update process_image() pakai masked ROI
  3. Di debug_roi_viewer  — update untuk tampilkan masked ROI
"""

import cv2
import numpy as np


def create_palm_mask(roi_gray, landmarks, roi_rect, img_shape):
    """
    Buat binary mask dari convex hull landmark MediaPipe,
    di-warp ke koordinat ROI.

    Cara kerja:
      1. Ambil koordinat landmark di gambar asli (sudah di-align)
      2. Geser koordinat ke sistem koordinat ROI (kurangi x1, y1)
      3. Buat convex hull dari landmark yang sudah di-geser
      4. Fill convex hull → binary mask ukuran ROI

    Args:
        roi_gray  : grayscale ROI (200x200)
        landmarks : list (x,y) dari 6 PALM_LANDMARK_IDS di gambar asli
        roi_rect  : (x1, y1, x2, y2) area crop di gambar asli
        img_shape : (h, w) gambar asli

    Returns:
        mask (np.uint8): binary mask ukuran ROI_SIZE x ROI_SIZE
                         255 = area telapak, 0 = background
    """
    h, w = roi_gray.shape[:2]
    mask = np.zeros((h, w), dtype=np.uint8)

    if landmarks is None:
        # Fallback: pakai lingkaran penuh (no masking)
        cv2.circle(mask, (w//2, h//2), min(w, h)//2, 255, -1)
        return mask

    x1, y1, x2, y2 = roi_rect
    crop_w = x2 - x1
    crop_h = y2 - y1

    # Geser landmark ke koordinat ROI, lalu scale ke ROI_SIZE
    pts = []
    for (lx, ly) in landmarks:
        # Geser ke koordinat crop
        rx = lx - x1
        ry = ly - y1
        # Scale ke ROI_SIZE (karena crop di-resize ke ROI_SIZE)
        sx = int(rx * w / crop_w)
        sy = int(ry * h / crop_h)
        # Clamp agar tidak keluar bounds
        sx = max(0, min(w-1, sx))
        sy = max(0, min(h-1, sy))
        pts.append([sx, sy])

    pts = np.array(pts, dtype=np.int32)

    # Expand convex hull sedikit ke luar (dilate) agar tidak terlalu ketat
    hull = cv2.convexHull(pts)

    # Isi convex hull
    cv2.fillConvexPoly(mask, hull, 255)

    # Dilate mask sedikit agar area tepi telapak tidak terpotong
    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (15, 15))
    mask = cv2.dilate(mask, kernel, iterations=1)

    # Clamp kembali ke ukuran ROI
    mask = mask[:h, :w]

    return mask


def apply_palm_mask(roi_gray, mask):
    """
    Apply mask ke ROI — area luar telapak dijadikan abu-abu netral (128).

    Kenapa 128 bukan 0 (hitam):
      Gabor filter pada area hitam (0) menghasilkan edge artifact
      di batas mask. Abu-abu netral (128) tidak punya gradient → 
      Gabor tidak menghasilkan respons palsu di tepi mask.

    Args:
        roi_gray : grayscale ROI
        mask     : binary mask (255=telapak, 0=background)

    Returns:
        masked_roi (np.uint8): ROI dengan background = 128 (netral)
    """
    masked = roi_gray.copy()
    masked[mask == 0] = 128
    return masked


# ═══════════════════════════════════════════════════════════════════
# UPDATE enhance_gabor() — dengan masking + DoG
# Ganti fungsi enhance_gabor() di palmprint_api.py dengan ini
# ═══════════════════════════════════════════════════════════════════

GABOR_THETAS = [0, np.pi/4, np.pi/2, 3*np.pi/4]
GABOR_KSIZE  = 21
GABOR_SIGMA  = 4.0
GABOR_LAMBDA = 10.0
GABOR_GAMMA  = 0.5
CLAHE_CLIP   = 2.0
CLAHE_TILE   = (8, 8)


def normalize_illumination(img_gray):
    """DoG illumination normalization (Fase 1B)."""
    img_f   = img_gray.astype(np.float32)
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=1.0)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=10.0)
    dog     = g_small - g_large
    return cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


def enhance_gabor(img_gray, mask=None):
    """
    [DIMODIFIKASI - Fase 1B + Masking]
    Pipeline: masking → DoG normalization → Gabor → CLAHE

    Args:
        img_gray : grayscale ROI
        mask     : optional palm mask dari create_palm_mask()
                   None = tidak ada masking (backward compatible)
    """
    # ── Masking background (jika mask tersedia) ──
    if mask is not None:
        img_gray = apply_palm_mask(img_gray, mask)

    # ── DoG normalization (Fase 1B) ──
    img_gray = normalize_illumination(img_gray)

    # ── Gabor filter bank ──
    responses = []
    for theta in GABOR_THETAS:
        kernel = cv2.getGaborKernel(
            ksize=(GABOR_KSIZE, GABOR_KSIZE),
            sigma=GABOR_SIGMA, theta=theta,
            lambd=GABOR_LAMBDA, gamma=GABOR_GAMMA,
            psi=0, ktype=cv2.CV_32F
        )
        resp = cv2.filter2D(img_gray.astype(np.float32), cv2.CV_32F, kernel)
        responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)
    gabor_max = cv2.normalize(gabor_max, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)

    # ── CLAHE ──
    clahe = cv2.createCLAHE(clipLimit=CLAHE_CLIP, tileGridSize=CLAHE_TILE)
    enhanced = clahe.apply(gabor_max)

    # ── Apply mask lagi setelah enhance agar tepi bersih ──
    if mask is not None:
        enhanced[mask == 0] = 0

    return enhanced


# ═══════════════════════════════════════════════════════════════════
# UPDATE process_image() di palmprint_api.py
# Ganti bagian pipeline ekstraksi dengan ini
# ═══════════════════════════════════════════════════════════════════

def process_image_example():
    """
    Contoh cara update process_image() di palmprint_api.py.
    
    SEBELUM (lama):
        roi      = extract_roi(img)
        enhanced = enhance_gabor(roi)
        feat     = extract_hog_sgf(enhanced)

    SESUDAH (baru):
        roi, dbg = detect_palm_opencv(img)          # dapat ROI + debug info
        mask     = create_palm_mask(               # buat mask dari landmark
                       roi,
                       dbg['landmarks'],
                       dbg['roi_rect'],
                       img.shape
                   )
        enhanced = enhance_gabor(roi, mask=mask)   # enhance dengan masking
        feat     = extract_hog_sgf(enhanced)
    """
    pass


# ═══════════════════════════════════════════════════════════════════
# QUICK TEST
# ═══════════════════════════════════════════════════════════════════

if __name__ == '__main__':
    import sys
    import os
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from roi_mediapipe import detect_palm_opencv

    if len(sys.argv) < 2 or not os.path.exists(sys.argv[1]):
        print('Usage: python fase1b_masking_patch.py path/to/foto.jpg')
        sys.exit(0)

    img = cv2.imread(sys.argv[1])
    print(f'Image: {img.shape}')

    roi, dbg = detect_palm_opencv(img)

    mask = create_palm_mask(roi, dbg['landmarks'], dbg['roi_rect'], img.shape)
    enhanced_tanpa_mask = enhance_gabor(roi, mask=None)
    enhanced_dengan_mask = enhance_gabor(roi, mask=mask)

    cv2.imwrite('test_mask_roi.jpg', roi)
    cv2.imwrite('test_mask_mask.jpg', mask)
    cv2.imwrite('test_mask_tanpa.jpg', enhanced_tanpa_mask)
    cv2.imwrite('test_mask_dengan.jpg', enhanced_dengan_mask)

    print('Saved:')
    print('  test_mask_roi.jpg     — ROI asli')
    print('  test_mask_mask.jpg    — binary mask telapak')
    print('  test_mask_tanpa.jpg   — enhanced tanpa mask')
    print('  test_mask_dengan.jpg  — enhanced dengan mask (harusnya lebih bersih)')
    print('  test_mask_dengan.jpg  — enhanced dengan mask (harusnya lebih bersih)')