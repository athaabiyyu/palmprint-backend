"""
palmprint_api.py
Dipanggil oleh Laravel via shell_exec (legacy) atau testing manual:
  python palmprint_api.py --image path/to/foto.jpg
  python palmprint_api.py --images foto1.jpg foto2.jpg foto3.jpg
Output: JSON ke stdout

Catatan:
  - Untuk production, gunakan FastAPI (main.py + extractor.py)
  - File ini dipertahankan untuk testing manual dan fallback
  - Parameter HARUS sama persis dengan extractor.py dan notebook
"""

import os
import sys
import json
import argparse
import warnings

warnings.filterwarnings("ignore")

import cv2
import numpy as np
import joblib
from skimage.feature import hog
from roi_mediapipe import detect_palm_opencv

# =====================================================================
# CONFIG — harus sama persis dengan palmprint_modeling.ipynb
# =====================================================================

BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(BASE_DIR, "models")

# ROI & image size
ROI_SIZE   = 200
IMAGE_SIZE = 64

# ✅ HOG — disinkronisasi dengan notebook (orient=9, pixels=8, cells=2)
HOG_ORIENT = 9    # dari 6
HOG_PIXELS = 8    # dari 16
HOG_CELLS  = 2

# SGF — 24 orientasi (0°–345°, step 15°)
SGF_ANGLES = np.deg2rad(np.arange(0, 360, 15))

# CLAHE
CLAHE_CLIP = 2.0
CLAHE_TILE = (8, 8)

# ✅ Gabor — disinkronisasi dengan notebook (ksize=31, lambda=20)
GABOR_KSIZE  = 31    # dari 21
GABOR_SIGMA  = 4.0
GABOR_LAMBDA = 20.0  # dari 10.0
GABOR_GAMMA  = 0.5
GABOR_THETAS = [0, np.pi / 4, np.pi / 2, 3 * np.pi / 4]

# =====================================================================
# OUTPUT HELPERS
# =====================================================================

def output_success(vector, threshold):
    print(json.dumps({
        "status"   : "success",
        "vector"   : vector,
        "threshold": threshold,
        "dim"      : len(vector),
    }))

def output_error(message):
    print(json.dumps({"status": "error", "message": message}))


# =====================================================================
# STEP 1 — NORMALISASI PENCAHAYAAN (DoG)
# =====================================================================

def normalize_illumination(img_gray: np.ndarray) -> np.ndarray:
    """
    Normalisasi pencahayaan dengan Difference of Gaussians (DoG).
    Memisahkan tekstur dari pencahayaan global.
    DoG = Gaussian(σ=1) - Gaussian(σ=10)
    """
    img_f   = img_gray.astype(np.float32)
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=1.0)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=10.0)
    dog     = g_small - g_large
    return cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


# =====================================================================
# STEP 2 — ENHANCEMENT (Gabor + CLAHE)
# =====================================================================

def enhance_gabor(img_gray: np.ndarray, use_dog: bool = True) -> np.ndarray:
    """
    Gabor filter bank multi-orientasi + CLAHE.
    ✅ use_dog=True secara default — konsisten dengan training.
    """
    if use_dog:
        img_gray = normalize_illumination(img_gray)

    responses = []
    for theta in GABOR_THETAS:
        kernel = cv2.getGaborKernel(
            ksize=(GABOR_KSIZE, GABOR_KSIZE),
            sigma=GABOR_SIGMA,
            theta=theta,
            lambd=GABOR_LAMBDA,
            gamma=GABOR_GAMMA,
            psi=0,
            ktype=cv2.CV_32F,
        )
        resp = cv2.filter2D(img_gray.astype(np.float32), cv2.CV_32F, kernel)
        responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)
    gabor_max = cv2.normalize(gabor_max, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)

    clahe = cv2.createCLAHE(clipLimit=CLAHE_CLIP, tileGridSize=CLAHE_TILE)
    return clahe.apply(gabor_max)


# =====================================================================
# STEP 3 — HOG-SGF EXTRACTION
# =====================================================================

def extract_hog_sgf(img_gray: np.ndarray) -> np.ndarray:
    """
    HOG-SGF sesuai notebook:
      HOG : 1764 dim (orient=9, cell=8px, block=2cells)
      SGF :   48 dim (24 sudut × mean + std)
      Total: 1812 dim dengan normalisasi 80/20 weighted
    """
    img_64 = cv2.resize(img_gray, (IMAGE_SIZE, IMAGE_SIZE))

    # ── HOG ──
    hog_feat = hog(
        img_64,
        orientations=HOG_ORIENT,
        pixels_per_cell=(HOG_PIXELS, HOG_PIXELS),
        cells_per_block=(HOG_CELLS, HOG_CELLS),
        block_norm="L2",
        visualize=False,
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

    # ✅ Normalisasi 80/20 weighted — konsisten dengan notebook
    hog_norm = hog_feat / (np.linalg.norm(hog_feat) + 1e-8)
    sgf_norm = sgf_feat / (np.linalg.norm(sgf_feat) + 1e-8)
    combined = np.concatenate([hog_norm * 0.8, sgf_norm * 0.2])
    norm     = np.linalg.norm(combined)
    if norm > 0:
        combined = combined / norm

    return combined


# =====================================================================
# STEP 4 — QUALITY GATE
# =====================================================================

def check_image_quality(roi_gray: np.ndarray) -> tuple:
    """
    Cek kualitas ROI sebelum ekstraksi fitur.
    Dijalankan pada ROI hasil MediaPipe (200×200).
    """
    lap_var     = cv2.Laplacian(roi_gray, cv2.CV_64F).var()
    mean_bright = float(roi_gray.mean())
    std_bright  = float(roi_gray.std())

    details = {
        "blur_score": round(lap_var, 1),
        "brightness": round(mean_bright, 1),
        "contrast"  : round(std_bright, 1),
    }

    if lap_var    < 5:   return False, "Foto terlalu blur.", details
    if mean_bright < 20: return False, "Foto terlalu gelap.", details
    if mean_bright > 245: return False, "Foto terlalu terang.", details
    if std_bright  < 5:  return False, "Detail telapak tidak terlihat.", details

    return True, "", details


# =====================================================================
# STEP 5 — LOAD MODEL
# =====================================================================

def load_models():
    """Load scaler.pkl, pca.pkl, threshold.pkl dari folder models/."""
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

def process_image(image_path: str) -> tuple:
    """
    Pipeline lengkap:
    foto → ROI (MediaPipe) → Quality Gate → DoG → Gabor+CLAHE
         → HOG-SGF → Scaler → PCA → vektor
    """
    img = cv2.imread(image_path)
    if img is None:
        output_error(f"Gagal membaca gambar: {image_path}")
        sys.exit(1)

    # ── STEP 1: Ekstraksi ROI dengan MediaPipe ──
    roi, dbg = detect_palm_opencv(img)

    # ── STEP 2: Cek apakah tangan terdeteksi ──
    if dbg["fallback"]:
        output_error(
            "Tangan tidak terdeteksi. "
            "Pastikan telapak tangan terlihat jelas dan menghadap kamera."
        )
        sys.exit(1)

    # ── STEP 3: Pastikan ROI 200×200 ──
    if roi.shape != (ROI_SIZE, ROI_SIZE):
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE))

    # ── STEP 4: Quality Gate pada ROI ──
    is_ok, reason, details = check_image_quality(roi)
    if not is_ok:
        debug_path = os.path.join(BASE_DIR, "debug_roi.jpg")
        cv2.imwrite(debug_path, roi)
        print(json.dumps({
            "status" : "error",
            "message": reason,
            "details": details,
            "type"   : "quality_gate",
        }))
        sys.exit(1)

    # ── STEP 5: Enhancement dengan DoG ──
    # ✅ use_dog=True — konsisten dengan training notebook
    enhanced = enhance_gabor(roi, use_dog=True)

    # ── STEP 6: Feature extraction ──
    feat = extract_hog_sgf(enhanced)

    # ── STEP 7: Transform dengan model ──
    scaler, pca, threshold = load_models()
    feat_scaled = scaler.transform([feat])
    feat_pca    = pca.transform(feat_scaled)

    # Simpan debug ROI
    debug_path = os.path.join(BASE_DIR, "debug_roi.jpg")
    cv2.imwrite(debug_path, roi)

    return feat_pca[0].tolist(), float(threshold)


# =====================================================================
# ENTRY POINT
# =====================================================================

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Palmprint feature extraction")
    parser.add_argument("--image",  required=False, help="Path ke satu gambar (absensi)")
    parser.add_argument("--images", nargs="+", required=False,
                        help="Path ke beberapa gambar (registrasi)")
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
                results.append({"status": "error", "message": f"File tidak ditemukan: {image_path}"})
                continue
            try:
                vector, threshold = process_image(image_path)
                results.append({
                    "status"   : "success",
                    "vector"   : vector,
                    "threshold": threshold,
                    "dim"      : len(vector),
                })
            except Exception as e:
                results.append({"status": "error", "message": str(e)})
        print(json.dumps(results))

    else:
        output_error("Harus provide --image atau --images")
        sys.exit(1)