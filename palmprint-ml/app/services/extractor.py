"""
app/services/extractor.py
=========================
Inti logika ML yang dipindah dari palmprint_api.py.

Perbedaan utama dari palmprint_api.py:
  - Model (scaler, pca, threshold) di-load SEKALI saat modul ini diimport
  - Tidak ada sys.exit() — error dilempar sebagai Exception
  - Tidak ada output JSON langsung — return dict biasa
  - Bisa dipanggil berulang kali tanpa overhead load model
"""

import os
import sys
import cv2
import numpy as np
import joblib
from skimage.feature import hog

# =====================================================================
# PATH SETUP
# =====================================================================

BASE_DIR   = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MODELS_DIR = os.path.join(BASE_DIR, "models")

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

try:
    from roi_mediapipe import detect_palm_opencv
    _mediapipe_available = True
    print("[extractor] ✓ roi_mediapipe loaded (MediaPipe hand detection aktif)")
except ImportError as e:
    _mediapipe_available = False
    print(f"[extractor] ⚠ roi_mediapipe tidak bisa di-load: {e}")

# =====================================================================
# KONFIGURASI — harus sama persis dengan palmprint_modeling.ipynb
# =====================================================================

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
# LOAD MODEL
# =====================================================================

print(f"[extractor] Loading models dari: {MODELS_DIR}")

try:
    _scaler    = joblib.load(os.path.join(MODELS_DIR, "scaler.pkl"))
    _pca       = joblib.load(os.path.join(MODELS_DIR, "pca.pkl"))
    _threshold = joblib.load(os.path.join(MODELS_DIR, "threshold.pkl"))
    print(f"[extractor] ✓ scaler.pkl loaded")
    print(f"[extractor] ✓ pca.pkl loaded  (n_components={_pca.n_components_})")
    print(f"[extractor] ✓ threshold.pkl loaded (value={_threshold:.4f})")
except Exception as e:
    _scaler = _pca = _threshold = None
    print(f"[extractor] ✗ Gagal load model: {e}")

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
    # ✅ DoG sebelum Gabor — konsisten dengan notebook
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
# STEP 3 — HOG-SGF FEATURE EXTRACTION
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
    Ix = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)

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
    norm = np.linalg.norm(combined)
    if norm > 0:
        combined = combined / norm

    return combined


# =====================================================================
# STEP 4 — QUALITY GATE
# =====================================================================

def check_image_quality(roi_gray: np.ndarray) -> tuple[bool, str, dict]:
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
        "contrast":   round(std_bright, 1),
    }

    if lap_var < 30:
        return False, "Foto terlalu blur. Pastikan kamera fokus dan tangan tidak bergerak.", details
    if mean_bright < 30:
        return False, "Foto terlalu gelap. Pindah ke tempat yang lebih terang.", details
    if mean_bright > 230:
        return False, "Foto terlalu terang. Hindari cahaya langsung ke kamera.", details
    if std_bright < 5:
        return False, "Detail telapak tangan tidak terlihat. Pastikan telapak menghadap kamera.", details

    return True, "", details


# =====================================================================
# FUNGSI UTAMA — dipanggil oleh FastAPI endpoint
# =====================================================================

def extract_from_roi(roi_bytes: bytes) -> dict:
    # ── Cek model tersedia ──
    if _scaler is None or _pca is None or _threshold is None:
        raise ValueError("Model belum di-load. Cek models/ directory.")

    # ── Decode bytes → numpy array ──
    nparr    = np.frombuffer(roi_bytes, np.uint8)
    img_bgr  = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    img_gray = cv2.imdecode(nparr, cv2.IMREAD_GRAYSCALE)

    if img_bgr is None or img_gray is None:
        raise RuntimeError("Gagal decode ROI image. Pastikan format valid (JPG/PNG).")

    # ── MediaPipe Hand Detection ──
    if _mediapipe_available:
        try:
            roi_mp, dbg = detect_palm_opencv(img_bgr)
            print(f"[MediaPipe] fallback={dbg['fallback']}, angle={dbg.get('angle', 0):.1f}°")
            if dbg['fallback']:
                raise ValueError(
                    "Tangan tidak terdeteksi. "
                    "Pastikan telapak tangan terlihat jelas dan menghadap kamera."
                )
            roi = roi_mp
        except ValueError:
            raise
        except Exception as e:
            print(f"[MediaPipe] Error: {e}, fallback ke resize langsung")
            roi = cv2.resize(img_gray, (ROI_SIZE, ROI_SIZE))
    else:
        roi = cv2.resize(img_gray, (ROI_SIZE, ROI_SIZE))

    # ── Pastikan ROI grayscale 200×200 ──
    if roi.shape != (ROI_SIZE, ROI_SIZE):
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE))

    # ── Quality Gate — pada ROI hasil MediaPipe ──
    is_ok, reason, details = check_image_quality(roi)
    print(f"[QualityGate] is_ok={is_ok}, details={details}")
    if not is_ok:
        raise ValueError(reason)

    # ✅ Enhancement dengan DoG — konsisten dengan training
    enhanced = enhance_gabor(roi, use_dog=True)

    # ── Feature Extraction ──
    feat = extract_hog_sgf(enhanced)

    # ── Transform: Scaler → PCA ──
    feat_scaled = _scaler.transform([feat])
    feat_pca    = _pca.transform(feat_scaled)

    return {
        "status"    : "success",
        "vector"    : feat_pca[0].tolist(),
        "threshold" : float(_threshold),
        "dim"       : len(feat_pca[0]),
    }