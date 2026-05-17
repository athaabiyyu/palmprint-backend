"""
palmprint_api.py
Dipanggil oleh Laravel via shell_exec:
  python palmprint_api.py --image path/to/foto.jpg
  python palmprint_api.py --images foto1.jpg foto2.jpg foto3.jpg
Output: JSON ke stdout

Revisi:
  - Palm detector: OpenCV (YCrCb + HSV skin + Morphological) — YOLO dihapus
  - Threshold: nilai optimal dari threshold.pkl (bukan auto-percentile)
  - PCA: variance otomatis 95% (n_components dari pca.pkl)
"""

import os
import sys
import json
import argparse
import warnings
from roi_mediapipe import extract_roi
warnings.filterwarnings('ignore')

import cv2
import numpy as np
import joblib
from skimage.feature import hog


# =====================================================================
# CONFIG — harus sama persis dengan palmprint_modeling.ipynb
# =====================================================================

BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(BASE_DIR, "models")

# ROI & image size
ROI_SIZE   = 200
IMAGE_SIZE = 64

# HOG — best params dari tuning (orient=6, pixels=16, cells=2, F1=0.8479)
HOG_ORIENT = 6
HOG_PIXELS = 16
HOG_CELLS  = 2

# SGF — 24 orientasi (0°–345°, step 15°)
SGF_ANGLES = np.deg2rad(np.arange(0, 360, 15))

# CLAHE
CLAHE_CLIP = 2.0
CLAHE_TILE = (8, 8)

# Gabor
GABOR_KSIZE  = 21
GABOR_SIGMA  = 4.0
GABOR_LAMBDA = 10.0
GABOR_GAMMA  = 0.5
GABOR_THETAS = [0, np.pi/4, np.pi/2, 3*np.pi/4]

# =====================================================================
# OUTPUT HELPERS
# =====================================================================

def output_success(vector, threshold):
    print(json.dumps({
        "status"    : "success",
        "vector"    : vector,
        "threshold" : threshold,
        "dim"       : len(vector)
    }))

def output_error(message):
    print(json.dumps({
        "status"  : "error",
        "message" : message
    }))


# =====================================================================
# STEP 2 — Normalize Illumination Menggunakan DoG
# =====================================================================
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


# =====================================================================
# STEP 3 — ENHANCEMENT (Gabor + CLAHE)
# =====================================================================
def enhance_gabor(
    img_gray,
    ksize=None,
    sigma=None,
    lambd=None,
    gamma=None,
    thetas=None,
    use_dog=False
):
    """
    Gabor filter bank multi-orientasi + CLAHE.
    Parameter bisa di-override untuk tuning.
    """

    ksize  = ksize  or GABOR_KSIZE
    sigma  = sigma  or GABOR_SIGMA
    lambd  = lambd  or GABOR_LAMBDA
    gamma  = gamma  or GABOR_GAMMA
    thetas = thetas or GABOR_THETAS

    # Optional DoG
    if use_dog:
        img_gray = normalize_illumination(img_gray)

    responses = []

    for theta in thetas:
        kernel = cv2.getGaborKernel(
            ksize=(ksize, ksize),
            sigma=sigma,
            theta=theta,
            lambd=lambd,
            gamma=gamma,
            psi=0,
            ktype=cv2.CV_32F
        )

        resp = cv2.filter2D(
            img_gray.astype(np.float32),
            cv2.CV_32F,
            kernel
        )

        responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)

    gabor_max = cv2.normalize(
        gabor_max,
        None,
        0,
        255,
        cv2.NORM_MINMAX
    ).astype(np.uint8)

    clahe = cv2.createCLAHE(
        clipLimit=CLAHE_CLIP,
        tileGridSize=CLAHE_TILE
    )

    return clahe.apply(gabor_max)


# =====================================================================
# STEP 3 — HOG-SGF EXTRACTION
# =====================================================================

def extract_hog_sgf(img_gray):
    """
    HOG-SGF sesuai paper (Gumaei et al., Sensors 2018).
      HOG: best params → orient=6, cell=16×16, block=2×2
      SGF: 48 dim  (24 sudut × mean + std)
      Total: HOG dim + 48 + L2-norm
    """
    img_64 = cv2.resize(img_gray, (IMAGE_SIZE, IMAGE_SIZE))

    # ── HOG ──
    hog_feat = hog(
        img_64,
        orientations    = HOG_ORIENT,
        pixels_per_cell = (HOG_PIXELS, HOG_PIXELS),
        cells_per_block = (HOG_CELLS,  HOG_CELLS),
        block_norm      = 'L2',
        visualize       = False
    )

    # ── SGF ──
    img_f = img_64.astype(np.float32)
    Ix    = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy    = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)

    sgf_feats = []
    for theta in SGF_ANGLES:
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        sgf_feats.append(np.mean(FR))
        sgf_feats.append(np.std(FR))

    sgf_feat = np.array(sgf_feats, dtype=np.float32)  # 48 dim

    # ── Gabung + L2 Normalize ──
    combined = np.concatenate([hog_feat, sgf_feat])
    norm     = np.linalg.norm(combined)
    if norm > 0:
        combined = combined / norm

    return combined


# =====================================================================
# STEP 4 — LOAD MODEL
# =====================================================================

def load_models():
    """
    Load scaler.pkl, pca.pkl, threshold.pkl dari folder models/.
    threshold.pkl berisi nilai optimal dari sweep F1-Score (bukan auto-percentile).
    """
    try:
        scaler    = joblib.load(os.path.join(MODELS_DIR, "scaler.pkl"))
        pca       = joblib.load(os.path.join(MODELS_DIR, "pca.pkl"))
        threshold = joblib.load(os.path.join(MODELS_DIR, "threshold.pkl"))
        return scaler, pca, threshold
    except Exception as e:
        output_error(f"Gagal load model: {str(e)}")
        sys.exit(1)


# =====================================================================
# MAIN PIPELINE
# =====================================================================

def process_image(image_path):
    """
    Pipeline lengkap:
    foto → ROI (OpenCV skin detect + centroid) → Gabor+CLAHE
         → HOG-SGF → Scaler → PCA → vektor PCA
    """
    img = cv2.imread(image_path)
    if img is None:
        output_error(f"Gagal membaca gambar: {image_path}")
        sys.exit(1)

    # Pipeline ekstraksi
    roi      = extract_roi(img)            # MediaPipe: alignment + dynamic ROI crop (Fase 1A)
    debug_path = os.path.join(BASE_DIR, "debug_roi.jpg")
    cv2.imwrite(debug_path, roi)
    enhanced = enhance_gabor(roi, use_dog=True)  # aktifkan DoG untuk foto HP
    feat     = extract_hog_sgf(enhanced)  # HOG-SGF (1812 dim, L2-norm)

    # Transform dengan model
    scaler, pca, threshold = load_models()
    feat_scaled = scaler.transform([feat])    # StandardScaler
    feat_pca    = pca.transform(feat_scaled)  # PCA → n_comp otomatis

    return feat_pca[0].tolist(), float(threshold)


# =====================================================================
# ENTRY POINT
# =====================================================================

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Palmprint feature extraction API")
    parser.add_argument("--image",  required=False, help="Path ke satu file gambar (mode absensi)")
    parser.add_argument("--images", nargs="+", required=False, help="Path ke beberapa file gambar (mode registrasi)")
    args = parser.parse_args()

    # ── Mode single image (absensi) ──
    if args.image:
        if not os.path.exists(args.image):
            output_error(f"File tidak ditemukan: {args.image}")
            sys.exit(1)
        vector, threshold = process_image(args.image)
        output_success(vector, threshold)

    # ── Mode multiple images (registrasi 3 foto) ──
    elif args.images:
        results = []
        for image_path in args.images:
            if not os.path.exists(image_path):
                results.append({
                    "status"  : "error",
                    "message" : f"File tidak ditemukan: {image_path}"
                })
                continue
            try:
                vector, threshold = process_image(image_path)
                results.append({
                    "status"    : "success",
                    "vector"    : vector,
                    "threshold" : threshold,
                    "dim"       : len(vector)
                })
            except Exception as e:
                results.append({
                    "status"  : "error",
                    "message" : str(e)
                })
        print(json.dumps(results))

    else:
        output_error("Harus provide --image atau --images")
        sys.exit(1)