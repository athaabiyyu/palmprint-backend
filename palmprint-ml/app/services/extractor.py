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
import cv2
import numpy as np
import joblib
from skimage.feature import hog

# =====================================================================
# PATH SETUP
# =====================================================================

# Lokasi models/ relatif dari file ini (app/services/extractor.py)
# Naik 2 level → palmprint-ml/models/
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MODELS_DIR = os.path.join(BASE_DIR, "models")

# =====================================================================
# KONFIGURASI — harus sama persis dengan palmprint_api.py & notebook
# =====================================================================

ROI_SIZE = 200
IMAGE_SIZE = 64

# HOG — hasil tuning terbaik (orient=6, pixels=16, cells=2, F1=0.8479)
HOG_ORIENT = 6
HOG_PIXELS = 16
HOG_CELLS = 2

# SGF — 24 orientasi (0°–345°, step 15°)
SGF_ANGLES = np.deg2rad(np.arange(0, 360, 15))

# CLAHE
CLAHE_CLIP = 2.0
CLAHE_TILE = (8, 8)

# Gabor
GABOR_KSIZE = 21
GABOR_SIGMA = 4.0
GABOR_LAMBDA = 10.0
GABOR_GAMMA = 0.5
GABOR_THETAS = [0, np.pi / 4, np.pi / 2, 3 * np.pi / 4]

# =====================================================================
# LOAD MODEL — hanya sekali saat startup, bukan tiap request
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
    # Kalau model tidak ditemukan, FastAPI tetap jalan tapi setiap
    # request akan gagal dengan pesan yang jelas
    _scaler = _pca = _threshold = None
    print(f"[extractor] ✗ Gagal load model: {e}")

# =====================================================================
# STEP 1 — ENHANCEMENT (Gabor + CLAHE)
# =====================================================================


def enhance_gabor(img_gray: np.ndarray) -> np.ndarray:
    """
    Gabor filter bank 4 orientasi + CLAHE.

    Kenapa Gabor:
      Garis palmprint punya orientasi berbeda-beda (horizontal, vertikal,
      diagonal). Gabor multi-orientasi menangkap semua arah sekaligus,
      lalu ambil respons maksimum → gambar yang menonjolkan semua garis.

    Kenapa CLAHE setelah Gabor:
      Gabor bisa menghasilkan kontras tidak merata. CLAHE meratakan
      kontras secara lokal agar HOG tidak bias ke area terang saja.

    use_dog=False — sengaja tidak diaktifkan. Dataset training pakai
    kamera khusus dengan pencahayaan konsisten. DoG justru memotong
    frekuensi yang mengandung detail garis halus palmprint.
    """
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
# STEP 2 — HOG-SGF FEATURE EXTRACTION
# =====================================================================


def extract_hog_sgf(img_gray: np.ndarray) -> np.ndarray:
    """
    HOG + SGF sesuai paper Gumaei et al. (Sensors 2018).

    HOG (Histogram of Oriented Gradients):
      Menangkap distribusi arah tepi/garis di tiap cell 16×16px.
      Hasil: vektor yang merepresentasikan tekstur dan struktur garis.

    SGF (Steerable Gaussian Filter):
      Proyeksikan gradient ke 24 arah (0°–345°, step 15°).
      Ambil mean + std tiap arah → 48 nilai.
      Menangkap informasi arah dominan garis palmprint.

    Gabungan HOG + SGF + L2-norm = vektor fitur final yang robust.
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

    # ── Gabung + L2 Normalize ──
    combined = np.concatenate([hog_feat, sgf_feat])
    norm = np.linalg.norm(combined)
    if norm > 0:
        combined = combined / norm

    return combined


# =====================================================================
# STEP 3 — QUALITY GATE
# =====================================================================


def check_image_quality(roi_gray: np.ndarray) -> tuple[bool, str, dict]:
    """
    Cek kualitas ROI sebelum ekstraksi fitur.
    Tolak foto yang blur, gelap, terlalu terang, atau flat.

    Dilakukan di sini (server) sebagai backup — quality gate utama
    seharusnya sudah dilakukan di Flutter sebelum upload.
    Kalau Flutter sudah reject, request tidak akan sampai sini.
    """
    lap_var = cv2.Laplacian(roi_gray, cv2.CV_64F).var()
    mean_bright = float(roi_gray.mean())
    std_bright = float(roi_gray.std())

    details = {
        "blur_score": round(lap_var, 1),
        "brightness": round(mean_bright, 1),
        "contrast": round(std_bright, 1),
    }

    if lap_var < 30:
        return False, "Foto terlalu blur.", details
    if mean_bright < 30:
        return False, "Foto terlalu gelap.", details
    if mean_bright > 230:
        return False, "Foto terlalu terang.", details
    if std_bright < 10:
        return False, "Detail telapak tidak terlihat.", details

    return True, "", details


# =====================================================================
# FUNGSI UTAMA — dipanggil oleh FastAPI endpoint
# =====================================================================


def extract_from_roi(roi_bytes: bytes) -> dict:
    """
    Terima ROI sebagai bytes (hasil upload Flutter),
    jalankan pipeline ML, return vektor fitur.

    Pipeline:
      bytes → decode → quality gate → Gabor+CLAHE → HOG-SGF → Scaler → PCA → vektor

    Return:
      {
        "status"    : "success",
        "vector"    : [...],   ← vektor PCA siap untuk cosine similarity
        "threshold" : 0.xxxx,
        "dim"       : N
      }

    Raise:
      ValueError — kalau model belum di-load atau quality gate gagal
      RuntimeError — kalau ROI tidak bisa di-decode
    """
    # ── Cek model tersedia ──
    if _scaler is None or _pca is None or _threshold is None:
        raise ValueError("Model belum di-load. Cek models/ directory.")

    # ── Decode bytes → numpy array (grayscale) ──
    nparr = np.frombuffer(roi_bytes, np.uint8)
    roi = cv2.imdecode(nparr, cv2.IMREAD_GRAYSCALE)

    if roi is None:
        raise RuntimeError("Gagal decode ROI image. Pastikan format valid (JPG/PNG).")

    # ── Resize ke ROI_SIZE jika belum ──
    if roi.shape != (ROI_SIZE, ROI_SIZE):
        roi = cv2.resize(roi, (ROI_SIZE, ROI_SIZE))

    # ── Quality Gate (backup, utama di Flutter) ──
    is_ok, reason, details = check_image_quality(roi)
    if not is_ok:
        raise ValueError(f"Quality gate gagal: {reason} | details: {details}")

    # ── Enhancement ──
    enhanced = enhance_gabor(roi)

    # ── Feature Extraction ──
    feat = extract_hog_sgf(enhanced)

    # ── Transform: Scaler → PCA ──
    feat_scaled = _scaler.transform([feat])
    feat_pca = _pca.transform(feat_scaled)

    return {
        "status": "success",
        "vector": feat_pca[0].tolist(),
        "threshold": float(_threshold),
        "dim": len(feat_pca[0]),
    }
