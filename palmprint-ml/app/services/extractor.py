"""
app/services/extractor.py
=========================
Inti logika ML untuk ekstraksi fitur palmprint.
SINKRON 100% dengan debug_pipeline_v2.ipynb:
  - IMAGE_SIZE=160, HOG_ORIENT=12, Tanpa StandardScaler
  - Gabor dual-scale (8 theta x 2 scale = 16 kernel), kombinasi 0.4*max + 0.6*mean
  - CLAHE clip=1.5, tile=8x8
  - augment_roi (13 jenis gangguan, n_aug=6) untuk skema registrasi 21 vector/user
"""

import os
import sys
import cv2
import numpy as np
import joblib
from app.target_pca import TargetPCA 
from skimage.feature import hog
import datetime

# =====================================================================
# PATH SETUP
# =====================================================================
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MODELS_DIR = os.path.join(BASE_DIR, "models")

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

# =====================================================================
# KONFIGURASI — WAJIB SAMA PERSIS DENGAN TRAINING PIPELINE (debug_pipeline_v2.ipynb)
# =====================================================================
ROI_SIZE = 200
IMAGE_SIZE = 160  # Sesuai notebook training

HOG_ORIENT = 12   # Sesuai notebook training
HOG_PIXELS = 8
HOG_CELLS = 2

SGF_ANGLES = np.deg2rad(np.arange(0, 360, 15))  # 24 sudut, step 15 derajat

CLAHE_CLIP = 1.5   # Notebook: 1.5 (bukan 2.0)
CLAHE_TILE = (8, 8)

# Gabor Filter Bank — dual-scale, 8 theta (step 22.5 derajat) = 16 kernel total
GABOR_KSIZE = 21
GABOR_GAMMA = 0.5
GABOR_THETAS = np.deg2rad([0, 22.5, 45, 67.5, 90, 112.5, 135, 157.5])
GABOR_SCALES = [
    {"sigma": 3.5, "lambda": 12.0},  # Principal lines
    {"sigma": 1.2, "lambda": 4.0},   # Wrinkles & ridge features
]

# Augmentasi registrasi — HARUS sama dengan augment_template_only() di notebook
N_AUG_PER_TEMPLATE = 6  # 1 foto asli -> 6 augmented -> 3 foto x 7 = 21 vector/user

# =====================================================================
# LOAD ARTEFAK MODEL (PCA & THRESHOLD SAJA)
# =====================================================================
print(f"[extractor] Loading models dari: {MODELS_DIR}")

try:
    # _scaler dihapus total karena merusak orientasi spasial L2-Norm
    _pca = joblib.load(os.path.join(MODELS_DIR, "pca.pkl"))
    _threshold = joblib.load(os.path.join(MODELS_DIR, "threshold.pkl"))
    print(f"[extractor] OK pca.pkl loaded (n_components={_pca.n_components_})")
    print(f"[extractor] OK threshold.pkl loaded (value={_threshold:.4f})")
except Exception as e:
    _pca = _threshold = None
    print(f"[extractor] GAGAL load model: {e}")


# =====================================================================
# STEP 1 — NORMALISASI PENCAHAYAAN (DoG)
# =====================================================================
def normalize_illumination(img_gray):
    img_f = img_gray.astype(np.float32)
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=1.0)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=5.0)
    dog = g_small - g_large

    # Pastikan mean selalu di tengah (polaritas konsisten)
    dog = dog - dog.mean()

    return cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


# =====================================================================
# STEP 2 — ENHANCEMENT (Gabor dual-scale + CLAHE)
# =====================================================================
def enhance_gabor(img_gray: np.ndarray, use_dog: bool = True) -> np.ndarray:
    """
    Gabor filter bank dual-scale (2 sigma/lambda x 8 theta = 16 kernel),
    dikombinasikan 0.4*max + 0.6*mean, dilanjutkan CLAHE.
    SINKRON dengan enhance_gabor() di notebook training.
    """
    if use_dog:
        img_gray = normalize_illumination(img_gray)

    responses = []
    img_f = img_gray.astype(np.float32)

    for scale in GABOR_SCALES:
        for theta in GABOR_THETAS:
            kernel = cv2.getGaborKernel(
                ksize=(GABOR_KSIZE, GABOR_KSIZE),
                sigma=scale["sigma"],
                theta=theta,
                lambd=scale["lambda"],
                gamma=GABOR_GAMMA,
                psi=0,
                ktype=cv2.CV_32F,
            )
            resp = cv2.filter2D(img_f, cv2.CV_32F, kernel)
            responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)
    gabor_mean = np.mean(responses, axis=0)
    combined = 0.4 * gabor_max + 0.6 * gabor_mean

    combined = cv2.normalize(combined, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)
    clahe = cv2.createCLAHE(clipLimit=CLAHE_CLIP, tileGridSize=CLAHE_TILE)
    return clahe.apply(combined)


# =====================================================================
# STEP 2B — SHARPEN & RESIZE (sebelumnya hilang dari extractor lama!)
# =====================================================================
def sharpen_and_resize(img_gray: np.ndarray) -> np.ndarray:
    """
    Unsharp masking lalu resize ke IMAGE_SIZE x IMAGE_SIZE dengan INTER_CUBIC.
    SINKRON dengan sharpen_and_resize() di notebook training.
    """
    gaussian = cv2.GaussianBlur(img_gray, (5, 5), 2.0)
    sharpened = cv2.addWeighted(img_gray, 1.8, gaussian, -0.8, 0)
    return cv2.resize(
        sharpened, (IMAGE_SIZE, IMAGE_SIZE), interpolation=cv2.INTER_CUBIC
    )


# =====================================================================
# STEP 3 — HOG-SGF FEATURE EXTRACTION
# =====================================================================
def extract_hog_sgf(img_gray: np.ndarray) -> np.ndarray:
    """
    Ekstraksi Fitur Kombinasi HOG + SGF.
    Input WAJIB sudah melalui sharpen_and_resize() (160x160).
    """
    img_target = cv2.resize(img_gray, (IMAGE_SIZE, IMAGE_SIZE))

    hog_feat = hog(
        img_target,
        orientations=HOG_ORIENT,
        pixels_per_cell=(HOG_PIXELS, HOG_PIXELS),
        cells_per_block=(HOG_CELLS, HOG_CELLS),
        block_norm="L2",
        visualize=False,
    )

    img_f = img_target.astype(np.float32)
    Ix = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)

    sgf_feats = []
    for theta in SGF_ANGLES:
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        sgf_feats.append(np.mean(FR))
        sgf_feats.append(np.std(FR))

    sgf_feat = np.array(sgf_feats, dtype=np.float32)

    hog_norm = hog_feat / (np.linalg.norm(hog_feat) + 1e-8)
    sgf_norm = sgf_feat / (np.linalg.norm(sgf_feat) + 1e-8)

    combined = np.concatenate([hog_norm * 0.8, sgf_norm * 0.2])
    norm = np.linalg.norm(combined)
    if norm > 0:
        combined = combined / norm

    return combined


# =====================================================================
# STEP 3B — FULL PREPROCESSING PIPELINE (1 ROI -> 1 feature vector)
# =====================================================================
def preprocess_and_extract(roi_gray: np.ndarray) -> np.ndarray:
    """
    Pipeline lengkap: ROI -> DoG -> Gabor+CLAHE -> Sharpen+Resize -> HOG+SGF.
    Dipakai untuk foto asli MAUPUN hasil augment_roi (urutan harus identik
    dengan load_dataset_clean() / augment_template_only() di notebook).
    """
    dog = normalize_illumination(roi_gray)
    enhanced = enhance_gabor(dog, use_dog=False)  # DoG sudah dihitung manual di atas
    sharpened = sharpen_and_resize(enhanced)
    return extract_hog_sgf(sharpened)


# =====================================================================
# STEP 4 — AUGMENTASI ROI (khusus registrasi)
# =====================================================================
def augment_roi(roi_gray: np.ndarray, n_aug: int = N_AUG_PER_TEMPLATE) -> list:
    """
    13 jenis gangguan kamera HP, diterapkan pada ROI MENTAH (sebelum DoG/Gabor).
    SINKRON 100% dengan augment_roi() di notebook training (jangan ubah
    parameter di sini tanpa mengubah & retrain notebook juga).
    """
    h, w = roi_gray.shape[:2]
    results = []

    for _ in range(n_aug):
        aug = roi_gray.copy().astype(np.float32)

        # 1. Brightness shift +-60
        if np.random.random() < 0.5:
            aug = np.clip(aug + np.random.uniform(-60, 60), 0, 255)

        # 2. Contrast scaling
        if np.random.random() < 0.5:
            aug = np.clip(aug * np.random.uniform(0.7, 1.3), 0, 255)

        # 3. Gaussian noise
        if np.random.random() < 0.5:
            noise = np.random.normal(0, np.random.uniform(5, 15), aug.shape)
            aug = np.clip(aug + noise, 0, 255)

        # 4. Gaussian blur ringan
        if np.random.random() < 0.4:
            aug = cv2.GaussianBlur(aug, (0, 0), np.random.uniform(0.5, 1.5))

        # 5. Rotasi kecil +-10 derajat
        if np.random.random() < 0.6:
            angle = np.random.uniform(-10, 10)
            M = cv2.getRotationMatrix2D((w // 2, h // 2), angle, 1.0)
            aug = cv2.warpAffine(aug, M, (w, h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REFLECT)

        # 6. Random crop + resize balik
        if np.random.random() < 0.5:
            margin = 15
            x1 = np.random.randint(0, margin)
            y1 = np.random.randint(0, margin)
            x2 = w - np.random.randint(0, margin)
            y2 = h - np.random.randint(0, margin)
            aug = cv2.resize(aug[y1:y2, x1:x2], (w, h), interpolation=cv2.INTER_LINEAR)

        # 7. Brightness gradient
        if np.random.random() < 0.5:
            gradient = np.linspace(np.random.uniform(0.5, 0.9), np.random.uniform(1.1, 1.5), w).astype(np.float32)
            aug = np.clip(aug * gradient[np.newaxis, :], 0, 255)

        # 8. Gamma correction
        if np.random.random() < 0.5:
            gamma = np.random.uniform(0.5, 1.8)
            aug_norm = np.clip(aug / 255.0, 1e-7, 1.0)
            aug = np.clip(255.0 * (aug_norm ** gamma), 0, 255)

        # 9. Specular highlight
        if np.random.random() < 0.4:
            cx = np.random.randint(20, w - 20)
            cy = np.random.randint(20, h - 20)
            radius = np.random.randint(10, 30)
            Y_grid, X_grid = np.ogrid[:h, :w]
            dist = np.sqrt((X_grid - cx) ** 2 + (Y_grid - cy) ** 2)
            mask = (dist <= radius).astype(np.float32)
            intensity = np.random.uniform(60, 110)
            falloff = np.clip(1 - dist / radius, 0, 1) ** 2
            aug = np.clip(aug + intensity * falloff * mask, 0, 255)

        # 10. Motion blur ringan
        if np.random.random() < 0.3:
            ksize = np.random.choice([3, 5])
            angle = np.random.uniform(0, 180)
            kernel = np.zeros((ksize, ksize), dtype=np.float32)
            kernel[ksize // 2, :] = 1.0 / ksize
            M_rot = cv2.getRotationMatrix2D((float(ksize // 2), float(ksize // 2)), angle, 1)
            kernel = cv2.warpAffine(kernel, M_rot, (ksize, ksize))
            aug = cv2.filter2D(aug.astype(np.float32), -1, kernel)

        # 11. JPEG compression artifact
        if np.random.random() < 0.4:
            quality = np.random.randint(55, 80)
            _, enc = cv2.imencode('.jpg', aug.astype(np.uint8), [cv2.IMWRITE_JPEG_QUALITY, quality])
            aug = cv2.imdecode(enc, cv2.IMREAD_GRAYSCALE).astype(np.float32)

        # 12. Unsharp masking
        if np.random.random() < 0.4:
            blur_for_sharp = cv2.GaussianBlur(aug, (5, 5), 1.0)
            aug = np.clip(aug + np.random.uniform(0.5, 1.5) * (aug - blur_for_sharp), 0, 255)

        # 13. Complex shadow matrix
        if np.random.random() < 0.4:
            Y_grid, X_grid = np.meshgrid(np.arange(h), np.arange(w), indexing='ij')
            shadow_pattern = (X_grid * np.random.uniform(-0.5, 0.5) + Y_grid * np.random.uniform(-0.5, 0.5))
            shadow_pattern = cv2.normalize(shadow_pattern, None, 0.6, 1.0, cv2.NORM_MINMAX)
            aug = np.clip(aug * shadow_pattern, 0, 255)

        results.append(aug.astype(np.uint8))

    return results


# =====================================================================
# STEP 5 — QUALITY GATE FILTERING
# =====================================================================
def check_image_quality(roi_gray: np.ndarray) -> tuple:
    lap_var = cv2.Laplacian(roi_gray, cv2.CV_64F).var()
    mean_bright = float(roi_gray.mean())
    std_bright = float(roi_gray.std())

    details = {
        "blur_score": round(lap_var, 1),
        "brightness": round(mean_bright, 1),
        "contrast": round(std_bright, 1),
    }

    if lap_var < 8:
        return False, "Foto terlalu blur.", details
    if mean_bright < 30:
        return False, "Foto terlalu gelap.", details
    if mean_bright > 235:
        return False, "Foto terlalu terang.", details
    if std_bright < 6:
        return False, "Detail telapak tidak terlihat.", details

    return True, "", details


# =====================================================================
# HELPERS
# =====================================================================
def _decode_and_prepare_roi(roi_bytes: bytes) -> np.ndarray:
    """Decode bytes -> grayscale ndarray, paksa ukuran ROI_SIZE x ROI_SIZE."""
    nparr = np.frombuffer(roi_bytes, np.uint8)
    img_gray = cv2.imdecode(nparr, cv2.IMREAD_GRAYSCALE)

    if img_gray is None:
        raise RuntimeError("Gagal decode file gambar. Pastikan format valid (JPG/PNG).")

    if img_gray.shape != (ROI_SIZE, ROI_SIZE):
        img_gray = cv2.resize(img_gray, (ROI_SIZE, ROI_SIZE))

    return img_gray


def _save_debug_input(img_gray: np.ndarray, tag: str = "roi") -> None:
    debug_dir = os.path.join(BASE_DIR, "debug_inputs")
    os.makedirs(debug_dir, exist_ok=True)
    fname = f"debug_{tag}_{datetime.datetime.now().strftime('%H%M%S_%f')}.jpg"
    cv2.imwrite(os.path.join(debug_dir, fname), img_gray)


def _vector_to_pca(feat: np.ndarray) -> list:
    """Proyeksi 1 feature vector ke ruang PCA (tanpa StandardScaler)."""
    feat_pca = _pca.transform([feat])
    return feat_pca[0].tolist()


# =====================================================================
# MAIN INFERENCE — MODE ABSENSI (1 foto -> 1 vector)
# =====================================================================
def extract_from_roi(roi_bytes: bytes) -> dict:
    """
    Dipakai untuk absensi: 1 foto, tanpa augmentasi, quality-gated.
    """
    if _pca is None or _threshold is None:
        raise ValueError("Model (PCA/Threshold) belum siap di server. Cek folder models/.")

    img_gray = _decode_and_prepare_roi(roi_bytes)
    _save_debug_input(img_gray, tag="absen")

    is_ok, reason, details = check_image_quality(img_gray)
    if not is_ok:
        raise ValueError(reason)

    feat = preprocess_and_extract(img_gray)
    vector = _vector_to_pca(feat)

    return {
        "status": "success",
        "mode": "verify",
        "vector": vector,
        "threshold": float(_threshold),
        "dim": len(vector),
    }


# =====================================================================
# MAIN INFERENCE — MODE REGISTRASI (3 foto -> 21 vector)
# =====================================================================
def extract_from_roi_batch_register(roi_bytes_list: list) -> dict:
    """
    Dipakai untuk registrasi/re-registrasi: terima N foto asli (biasanya 3),
    tiap foto di-expand jadi 1 asli + N_AUG_PER_TEMPLATE augmented.
    Quality gate HANYA dijalankan pada foto asli (augmented sengaja
    "dirusak" untuk simulasi kondisi HP, jadi tidak melalui gate).

    Return:
        {
          "status": "success",
          "mode": "register",
          "vectors": [...],          # urut: [foto1: 1 asli + 6 aug, foto2: ..., foto3: ...]
          "per_photo_count": 7,
          "total_vectors": 21,
          "threshold": 0.16,
          "dim": 588
        }
    Kalau ada foto yang gagal quality gate, langsung raise ValueError
    dengan info foto ke berapa yang gagal (caller/Laravel sudah pakai
    pola ini: tolak semua kalau salah satu foto gagal).
    """
    if _pca is None or _threshold is None:
        raise ValueError("Model (PCA/Threshold) belum siap di server. Cek folder models/.")

    all_vectors = []

    for idx, roi_bytes in enumerate(roi_bytes_list, start=1):
        img_gray = _decode_and_prepare_roi(roi_bytes)
        
        is_ok, reason, details = check_image_quality(img_gray)
        if not is_ok:
            raise ValueError(f"Foto {idx}: {reason}")

        feat_original = preprocess_and_extract(img_gray)
        all_vectors.append(_vector_to_pca(feat_original))
        print(f"[register] Foto {idx}: 1 vector asli ditambahkan")  # ← tambah ini

        for aug_idx, aug_roi in enumerate(augment_roi(img_gray, n_aug=N_AUG_PER_TEMPLATE), start=1):
            feat_aug = preprocess_and_extract(aug_roi)
            all_vectors.append(_vector_to_pca(feat_aug))
            print(f"[register] Foto {idx}: augmentasi {aug_idx}/{N_AUG_PER_TEMPLATE} selesai")  # ← tambah ini

    print(f"[register] Total vectors: {len(all_vectors)}")  # ← tambah ini

       
    return {
        "status": "success",
        "mode": "register",
        "vectors": all_vectors,
        "per_photo_count": 1 + N_AUG_PER_TEMPLATE,
        "total_vectors": len(all_vectors),
        "threshold": float(_threshold),
        "dim": len(all_vectors[0]) if all_vectors else 0,
    }