"""
fase1b_patch.py — Fase 1B: Illumination Normalization
======================================================
Ini adalah PATCH — bukan file standalone.
Salin fungsi normalize_illumination() dan enhance_gabor() yang baru
ke dalam:
  1. palmprint_api.py        — ganti fungsi enhance_gabor() yang lama
  2. palmprint_modeling.ipynb — ganti fungsi enhance_gabor() yang lama

Tidak ada perubahan di file lain.
"""

import cv2
import numpy as np

# ═══════════════════════════════════════════════════════════════════
# [BARU - FASE 1B] ILLUMINATION NORMALIZATION
# ═══════════════════════════════════════════════════════════════════

def normalize_illumination(img_gray):
    """
    Normalisasi pencahayaan menggunakan DoG (Difference of Gaussians).

    Masalah yang diselesaikan:
      Foto dari HP punya variasi brightness yang beragam — terang, gelap,
      ada bayangan di tangan. Gabor filter akan menghasilkan respons berbeda
      untuk foto yang sama tapi pencahayaannya beda, padahal polanya sama.

    Cara kerja DoG:
      1. Blur gambar dengan Gaussian kecil (σ=1) → menangkap detail + cahaya
      2. Blur gambar dengan Gaussian besar (σ=10) → menangkap cahaya global saja
      3. Selisih keduanya = tekstur murni, bebas dari pencahayaan global

      DoG = G(σ=1) - G(σ=10)

    Kenapa DoG lebih baik dari CLAHE saja:
      CLAHE meningkatkan kontras lokal tapi tidak menghilangkan variasi
      pencahayaan global (tangan terang vs gelap tetap beda).
      DoG benar-benar memisahkan pencahayaan dari tekstur.

    Kenapa σ=1 dan σ=10:
      σ=1  → tangkap detail halus (lebar blur ~3px, cukup untuk palmprint)
      σ=10 → tangkap iluminasi global (lebar blur ~30px, smoothing besar)
      Selisih keduanya = band-pass filter yang menyisakan tekstur skala menengah

    Args:
        img_gray: grayscale image (np.uint8)

    Returns:
        normalized (np.uint8): gambar dengan pencahayaan ternormalisasi,
                               range 0-255, siap masuk Gabor filter
    """
    img_f = img_gray.astype(np.float32)

    # Gaussian blur kecil — tangkap detail + sedikit cahaya
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=1.0)

    # Gaussian blur besar — tangkap pencahayaan global saja
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=10.0)

    # DoG = selisih → tekstur murni (bisa negatif)
    dog = g_small - g_large

    # Normalize ke 0-255 untuk input Gabor
    normalized = cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)

    return normalized


# ═══════════════════════════════════════════════════════════════════
# [DIMODIFIKASI - FASE 1B] ENHANCE GABOR
# Tambahkan normalize_illumination() sebelum Gabor filter
# ═══════════════════════════════════════════════════════════════════

# CONFIG — sama persis dengan sebelumnya, tidak ada yang berubah
GABOR_THETAS  = [0, np.pi/4, np.pi/2, 3*np.pi/4]
GABOR_KSIZE   = 21
GABOR_SIGMA   = 4.0
GABOR_LAMBDA  = 10.0
GABOR_GAMMA   = 0.5
CLAHE_CLIP    = 2.0
CLAHE_TILE    = (8, 8)

def enhance_gabor(img_gray):
    """
    [DIMODIFIKASI - FASE 1B]
    Gabor filter bank multi-orientasi + CLAHE, dengan illumination
    normalization (DoG) di awal pipeline.

    Pipeline baru:
      img_gray → DoG normalization → Gabor filter bank → max response → CLAHE

    Perubahan dari versi lama:
      - Tambah normalize_illumination(DoG) di baris pertama
      - Gabor dan CLAHE tetap sama persis

    Kenapa DoG di awal, bukan setelah Gabor:
      Gabor bekerja lebih baik pada input yang sudah bersih dari variasi
      cahaya global. DoG di awal = beri input terbaik ke Gabor.

    Args:
        img_gray: grayscale ROI (np.uint8), ukuran ROI_SIZE x ROI_SIZE

    Returns:
        enhanced (np.uint8): hasil Gabor+CLAHE setelah normalisasi cahaya
    """
    # ── FASE 1B: Normalisasi pencahayaan sebelum Gabor ──
    img_gray = normalize_illumination(img_gray)

    # ── Gabor filter bank (sama persis dengan versi lama) ──
    responses = []
    for theta in GABOR_THETAS:
        kernel = cv2.getGaborKernel(
            ksize  = (GABOR_KSIZE, GABOR_KSIZE),
            sigma  = GABOR_SIGMA,
            theta  = theta,
            lambd  = GABOR_LAMBDA,
            gamma  = GABOR_GAMMA,
            psi    = 0,
            ktype  = cv2.CV_32F
        )
        resp = cv2.filter2D(img_gray.astype(np.float32), cv2.CV_32F, kernel)
        responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)
    gabor_max = cv2.normalize(gabor_max, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)

    # ── CLAHE (sama persis dengan versi lama) ──
    clahe = cv2.createCLAHE(clipLimit=CLAHE_CLIP, tileGridSize=CLAHE_TILE)
    return clahe.apply(gabor_max)


# ═══════════════════════════════════════════════════════════════════
# QUICK TEST — bandingkan sebelum vs sesudah normalisasi
# Jalankan: python fase1b_patch.py path/to/roi.jpg
# ═══════════════════════════════════════════════════════════════════

if __name__ == '__main__':
    import sys
    import os

    if len(sys.argv) < 2 or not os.path.exists(sys.argv[1]):
        print('Usage: python fase1b_patch.py path/to/roi.jpg')
        sys.exit(0)

    img = cv2.imread(sys.argv[1], cv2.IMREAD_GRAYSCALE)
    if img is None:
        print('Gagal baca gambar')
        sys.exit(1)

    print(f'Input  : {img.shape}, mean={img.mean():.1f}, std={img.std():.1f}')

    # Test normalisasi
    normalized = normalize_illumination(img)
    print(f'DoG    : mean={normalized.mean():.1f}, std={normalized.std():.1f}')

    # Test enhanced
    enhanced_lama  = enhance_gabor.__wrapped__(img) if hasattr(enhance_gabor, '__wrapped__') else None
    enhanced_baru  = enhance_gabor(img)

    cv2.imwrite('test_1b_input.jpg',      img)
    cv2.imwrite('test_1b_normalized.jpg', normalized)
    cv2.imwrite('test_1b_enhanced.jpg',   enhanced_baru)

    print('Saved: test_1b_input.jpg, test_1b_normalized.jpg, test_1b_enhanced.jpg')