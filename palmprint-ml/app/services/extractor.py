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
import datetime

# =====================================================================
# PATH SETUP
# =====================================================================

BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
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

ROI_SIZE = 200
IMAGE_SIZE = 64

HOG_ORIENT = 9
HOG_PIXELS = 8
HOG_CELLS = 2

SGF_ANGLES = np.deg2rad(np.arange(0, 360, 15))

CLAHE_CLIP = 2.0
CLAHE_TILE = (8, 8)

GABOR_KSIZE = 31
GABOR_SIGMA = 4.0
GABOR_LAMBDA = 20.0
GABOR_GAMMA = 0.5
GABOR_THETAS = [0, np.pi / 4, np.pi / 2, 3 * np.pi / 4]

# =====================================================================
# LOAD MODEL
# =====================================================================

print(f"[extractor] Loading models dari: {MODELS_DIR}")

try:
    _scaler = joblib.load(os.path.join(MODELS_DIR, "scaler.pkl"))
    _pca = joblib.load(os.path.join(MODELS_DIR, "pca.pkl"))
    _threshold = joblib.load(os.path.join(MODELS_DIR, "threshold.pkl"))
    print(f"[extractor] ✓ scaler.pkl loaded")
    print(f"[extractor] ✓ pca.pkl loaded  (n_components={_pca.n_components_})")
    print(f"[extractor] ✓ threshold.pkl loaded (value={_threshold:.4f})")
except Exception as e:
    _scaler = _pca = _threshold = None
    print(f"[extractor] ✗ Gagal load model: {e}")

# =====================================================================
# STEP 1 — NORMALISASI PENCAHAYAAN (DoG + equalizeHist)
# =====================================================================


def normalize_illumination(img_gray: np.ndarray) -> np.ndarray:
    """
    DoG murni — SAMA PERSIS dengan palmprint_modeling.ipynb.
    equalizeHist dihapus karena tidak ada di training pipeline.
    """
    img_f   = img_gray.astype(np.float32)
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=1.0)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=15.0)
    dog     = g_small - g_large
    return cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


# =====================================================================
# STEP 2 — ENHANCEMENT (Gabor + CLAHE)
# =====================================================================


def enhance_gabor(img_gray: np.ndarray, use_dog: bool = True) -> np.ndarray:
    """
    Gabor filter bank multi-orientasi + CLAHE.
    use_dog=True secara default — konsisten dengan training.
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
# STEP 3 — HOG-SGF FEATURE EXTRACTION
# =====================================================================


def extract_hog_sgf(img_gray: np.ndarray) -> np.ndarray:
    """
    HOG-SGF sesuai notebook:
      HOG : 1764 dim (orient=9, cell=8px, block=2cells)
      SGF :   48 dim (24 sudut x mean + std)
      Total: 1812 dim dengan normalisasi 80/20 weighted
    """
    img_64 = cv2.resize(img_gray, (IMAGE_SIZE, IMAGE_SIZE))

    # HOG
    hog_feat = hog(
        img_64,
        orientations=HOG_ORIENT,
        pixels_per_cell=(HOG_PIXELS, HOG_PIXELS),
        cells_per_block=(HOG_CELLS, HOG_CELLS),
        block_norm="L2",
        visualize=False,
    )

    # SGF
    img_f = img_64.astype(np.float32)
    Ix = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)

    sgf_feats = []
    for theta in SGF_ANGLES:
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        sgf_feats.append(np.mean(FR))
        sgf_feats.append(np.std(FR))

    sgf_feat = np.array(sgf_feats, dtype=np.float32)

    # Normalisasi 80/20 weighted
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
    lap_var = cv2.Laplacian(roi_gray, cv2.CV_64F).var()
    mean_bright = float(roi_gray.mean())
    std_bright = float(roi_gray.std())

    details = {
        "blur_score": round(lap_var, 1),
        "brightness": round(mean_bright, 1),
        "contrast": round(std_bright, 1),
    }

    if lap_var < 5:
        return False, "Foto terlalu blur.", details
    if mean_bright < 20:
        return False, "Foto terlalu gelap.", details
    if mean_bright > 245:
        return False, "Foto terlalu terang.", details
    if std_bright < 5:
        return False, "Detail telapak tidak terlihat.", details

    return True, "", details


# =====================================================================
# FUNGSI UTAMA — dipanggil oleh FastAPI endpoint
# =====================================================================


def extract_from_roi(roi_bytes: bytes) -> dict:
    """
    Flutter mengirim full frame JPEG berwarna (max 1080px).
    Server mengerjakan semua pipeline:
      1. detect_palm_opencv() — alignment + landmark + dynamic ROI crop
      2. Quality gate pada ROI
      3. equalizeHist + DoG + Gabor + CLAHE
      4. HOG-SGF → Scaler → PCA
    """
    if _scaler is None or _pca is None or _threshold is None:
        raise ValueError("Model belum di-load. Cek models/ directory.")

    if not _mediapipe_available:
        raise RuntimeError(
            "roi_mediapipe tidak tersedia. "
            "Pastikan mediapipe ter-install dan hand_landmarker.task ada."
        )

    nparr = np.frombuffer(roi_bytes, np.uint8)

    # ── Decode sebagai BGR (full frame berwarna dari Flutter) ──
    img_bgr = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img_bgr is None:
        raise RuntimeError("Gagal decode image. Pastikan format valid (JPG/PNG).")

    print(f"[extractor] Input shape: {img_bgr.shape}")
    
    debug_orig_filename = f"debug_orig_{datetime.datetime.now().strftime('%H%M%S_%f')}.jpg"
    debug_orig_path = os.path.join(BASE_DIR, "debug_inputs_ori", debug_orig_filename)
    os.makedirs(os.path.join(BASE_DIR, "debug_inputs_ori"), exist_ok=True)
    cv2.imwrite(debug_orig_path, img_bgr)
    print(f"[Debug] Saved original: {debug_orig_path} | shape={img_bgr.shape}")

    # ── Pipeline MediaPipe: alignment → landmark → dynamic ROI crop ──
    img_gray, dbg = detect_palm_opencv(img_bgr)

    print(
        f"[MediaPipe] angle={dbg['angle']:.1f}°  "
        f"dynamic_size={dbg['dynamic_roi_size']}px  "
        f"fallback={dbg['fallback']}  "
        f"area={dbg['area']:.0f}px²"
    )

    # Tolak jika tangan tidak terdeteksi
    if dbg["fallback"]:
        raise ValueError(
            "Tangan tidak terdeteksi. "
            "Pastikan telapak tangan terlihat jelas dan menghadap kamera."
        )

    # Simpan debug SEBELUM resize — untuk tahu ukuran asli dynamic ROI
    debug_filename = f"debug_input_{datetime.datetime.now().strftime('%H%M%S_%f')}.jpg"
    debug_path = os.path.join(BASE_DIR, "debug_inputs", debug_filename)
    os.makedirs(os.path.join(BASE_DIR, "debug_inputs"), exist_ok=True)
    cv2.imwrite(debug_path, img_gray)
    print(f"[Debug] Saved ROI: {debug_path} | shape={img_gray.shape}")
    
    # ── Pastikan ukuran 200×200 ──
    if img_gray.shape != (ROI_SIZE, ROI_SIZE):
        img_gray = cv2.resize(img_gray, (ROI_SIZE, ROI_SIZE))

    # ── Quality Gate ──
    is_ok, reason, details = check_image_quality(img_gray)
    print(f"[QualityGate] is_ok={is_ok}, details={details}")
    if not is_ok:
        raise ValueError(reason)

    # ── Enhancement + Feature Extraction ──
    enhanced = enhance_gabor(img_gray, use_dog=True)
    feat = extract_hog_sgf(enhanced)
    feat_scaled = _scaler.transform([feat])
    feat_pca = _pca.transform(feat_scaled)

    return {
        "status": "success",
        "vector": feat_pca[0].tolist(),
        "threshold": float(_threshold),
        "dim": len(feat_pca[0]),
    }
