"""
app/services/extractor.py
=========================
Inti logika ML untuk ekstraksi fitur palmprint.
SINKRON 100% dengan training pipeline (Config class):
  - IMAGE_SIZE=128, HOG_ORIENT=9, HOG_PIXELS=16, HOG_CELLS=2
  - SGF_ANGLES = 0-180 step 15 (12 sudut), pakai |FR| sebelum mean/std
  - HOG block_norm='L2-Hys', bobot kombinasi HOG:SGF = 0.85:0.15
  - Gabor dual-scale (8 theta x 2 scale = 16 kernel): (3.5,12.0) & (2.0,7.0)
  - Sharpen addWeighted(1.5, gaussian, -0.5)
  - CLAHE clip=1.5, tile=8x8
  - Tanpa StandardScaler
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
# KONFIGURASI — WAJIB SAMA PERSIS DENGAN TRAINING PIPELINE (Config class)
# =====================================================================
ROI_SIZE = 128
IMAGE_SIZE = 128 

HOG_ORIENT = 9    
HOG_PIXELS = 16  
HOG_CELLS = 2
HOG_BLOCK_NORM = "L2-Hys"  

HOG_SGF_WEIGHT = 0.85  
SGF_ANGLES = np.deg2rad(np.arange(0, 180, 15))  

CLAHE_CLIP = 1.5
CLAHE_TILE = (8, 8)

# Gabor Filter Bank — dual-scale, 8 theta (step 22.5 derajat) = 16 kernel total
GABOR_KSIZE = 21
GABOR_GAMMA = 0.5
GABOR_THETAS = np.deg2rad([0, 22.5, 45, 67.5, 90, 112.5, 135, 157.5])
GABOR_SCALES = [
    {"sigma": 3.5, "lambda": 12.0},  # Principal lines
    {"sigma": 2.0, "lambda": 7.0},   # Wrinkles & ridge features (sebelumnya salah: 1.2/4.0)
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
def normalize_illumination(img_gray, sigma_small=1.0, sigma_large=5.0):
    img_f = img_gray.astype(np.float32)

    # 1. Bikin dua versi blur
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=sigma_small)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=sigma_large)

    # 2. Kurangi untuk menghilangkan bayangan/iluminasi (DoG)
    dog = g_small - g_large
    dog = dog - dog.mean()

    # 3. Percentile Clip (1, 99) untuk potong outlier piksel
    lo, hi = np.percentile(dog, [1, 99])
    dog_clipped = np.clip(dog, lo, hi)

    # 4. Normalisasi akhir ke range 0-255 agar kontras maksimal
    return cv2.normalize(dog_clipped, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


# =====================================================================
# STEP 2 — ENHANCEMENT (Gabor dual-scale + CLAHE)
# =====================================================================
def enhance_gabor(img_gray: np.ndarray, use_dog: bool = True) -> np.ndarray:
    """
    Gabor filter bank dual-scale (2 sigma/lambda x 8 theta = 16 kernel),
    dikombinasikan 0.4*max + 0.6*mean, dilanjutkan CLAHE.
    SINKRON dengan enhance_gabor() di training (Config.GABOR_*).
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
# STEP 2B — SHARPEN & RESIZE
# =====================================================================
def sharpen_and_resize(img_gray: np.ndarray) -> np.ndarray:
    """
    Unsharp masking lalu resize ke IMAGE_SIZE x IMAGE_SIZE dengan INTER_CUBIC.
    SINKRON dengan sharpen_and_resize() di training: addWeighted(1.5, gaussian, -0.5).
    """
    gaussian = cv2.GaussianBlur(img_gray, (5, 5), 2.0)
    sharpened = cv2.addWeighted(img_gray, 1.5, gaussian, -0.5, 0)  # sebelumnya salah: 1.8 / -0.8
    return cv2.resize(
        sharpened, (IMAGE_SIZE, IMAGE_SIZE), interpolation=cv2.INTER_CUBIC
    )


# =====================================================================
# STEP 3 — HOG-SGF FEATURE EXTRACTION
# =====================================================================
def extract_hog_sgf(img_gray: np.ndarray) -> np.ndarray:
    """
    Ekstraksi Fitur Kombinasi HOG + SGF.
    SINKRON dengan extract_hog_sgf() di training:
      - HOG: orientations=9, pixels_per_cell=16x16, cells_per_block=2x2, block_norm='L2-Hys'
      - SGF: 12 sudut (0-180 step 15), pakai |FR| sebelum mean/std
      - Kombinasi: hog*0.85 + sgf*0.15, lalu L2-normalize ulang
    """
    img_target = cv2.resize(img_gray, (IMAGE_SIZE, IMAGE_SIZE))

    hog_feat = hog(
        img_target,
        orientations=HOG_ORIENT,
        pixels_per_cell=(HOG_PIXELS, HOG_PIXELS),
        cells_per_block=(HOG_CELLS, HOG_CELLS),
        block_norm=HOG_BLOCK_NORM,
        visualize=False,
    )

    img_f = img_target.astype(np.float32)
    Ix = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)

    sgf_feats = []
    for theta in SGF_ANGLES:
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        FR_abs = np.abs(FR)
        sgf_feats.append(np.mean(FR_abs))
        sgf_feats.append(np.std(FR_abs))

    sgf_feat = np.array(sgf_feats, dtype=np.float32)

    hog_norm = hog_feat / np.maximum(np.linalg.norm(hog_feat), 1e-8)
    sgf_norm = sgf_feat / np.maximum(np.linalg.norm(sgf_feat), 1e-8)

    combined = np.concatenate([
        hog_norm * HOG_SGF_WEIGHT,
        sgf_norm * (1 - HOG_SGF_WEIGHT),
    ])
    total_norm = np.linalg.norm(combined)
    if total_norm > 0:
        combined = combined / total_norm

    return combined


# =====================================================================
# STEP 3B — FULL PREPROCESSING PIPELINE (1 ROI -> 1 feature vector)
# =====================================================================
def preprocess_and_extract(roi_gray: np.ndarray) -> np.ndarray:
    """
    Pipeline lengkap: ROI -> DoG -> Gabor+CLAHE -> Sharpen+Resize -> HOG+SGF.
    Dipakai untuk foto asli MAUPUN hasil augment_roi (urutan harus identik
    dengan pipeline training).
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

        # # 12. Unsharp masking
        # if np.random.random() < 0.4:
        #     blur_for_sharp = cv2.GaussianBlur(aug, (5, 5), 1.0)
        #     aug = np.clip(aug + np.random.uniform(0.5, 1.5) * (aug - blur_for_sharp), 0, 255)

        # # 13. Complex shadow matrix
        # if np.random.random() < 0.4:
        #     Y_grid, X_grid = np.meshgrid(np.arange(h), np.arange(w), indexing='ij')
        #     shadow_pattern = (X_grid * np.random.uniform(-0.5, 0.5) + Y_grid * np.random.uniform(-0.5, 0.5))
        #     shadow_pattern = cv2.normalize(shadow_pattern, None, 0.6, 1.0, cv2.NORM_MINMAX)
        #     aug = np.clip(aug * shadow_pattern, 0, 255)

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
# MAIN INFERENCE — MODE IDENTIFIKASI 1:N (tambahan untuk 1:N)
# =====================================================================
def identify_from_roi(roi_bytes: bytes, gallery: list) -> dict:
    """
    Identifikasi 1:N: bandingkan 1 query foto ke SEMUA gallery di database.
    
    Args:
        roi_bytes : bytes foto dari Flutter
        gallery   : list of dict dari Laravel, format:
                    [{"user_id": 1, "vectors": [[...], [...], ...]}, ...]
    
    Return:
        {
          "status": "success" | "unknown",
          "user_id": int | None,
          "score": float,
          "threshold": float
        }
    """
    if _pca is None or _threshold is None:
        raise ValueError("Model (PCA/Threshold) belum siap di server. Cek folder models/.")

    # Ekstrak fitur query
    img_gray = _decode_and_prepare_roi(roi_bytes)
    _save_debug_input(img_gray, tag="identify")

    is_ok, reason, details = check_image_quality(img_gray)
    if not is_ok:
        raise ValueError(reason)

    feat = preprocess_and_extract(img_gray)
    query_vector = np.array(_vector_to_pca(feat))

    # Bandingkan ke semua gallery
    best_user_id = None
    best_score   = -1.0

    for entry in gallery:
        user_id     = entry["user_id"]
        vectors     = np.array(entry["vectors"])  # shape: (21, n_components)

        # Cosine similarity query vs semua vector user ini, ambil max
        norms       = np.linalg.norm(vectors, axis=1, keepdims=True)
        vectors_n   = vectors / np.maximum(norms, 1e-8)
        query_n     = query_vector / np.maximum(np.linalg.norm(query_vector), 1e-8)
        sims        = vectors_n @ query_n  # dot product = cosine similarity
        score       = float(np.max(sims))

        if score > best_score:
            best_score   = score
            best_user_id = user_id

    # Cek threshold
    if best_score >= _threshold:
        return {
            "status"    : "success",
            "user_id"   : best_user_id,
            "score"     : round(best_score, 6),
            "threshold" : float(_threshold),
        }
    else:
        return {
            "status"    : "unknown",
            "user_id"   : None,
            "score"     : round(best_score, 6),
            "threshold" : float(_threshold),
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
        _save_debug_input(img_gray, tag=f"register_foto{idx}")

        is_ok, reason, details = check_image_quality(img_gray)
        if not is_ok:
            raise ValueError(f"Foto {idx}: {reason}")

        feat_original = preprocess_and_extract(img_gray)
        all_vectors.append(_vector_to_pca(feat_original))
        print(f"[register] Foto {idx}: 1 vector asli ditambahkan")

        for aug_idx, aug_roi in enumerate(augment_roi(img_gray, n_aug=N_AUG_PER_TEMPLATE), start=1):
            feat_aug = preprocess_and_extract(aug_roi)
            all_vectors.append(_vector_to_pca(feat_aug))
            print(f"[register] Foto {idx}: augmentasi {aug_idx}/{N_AUG_PER_TEMPLATE} selesai")

    print(f"[register] Total vectors: {len(all_vectors)}")

    return {
        "status": "success",
        "mode": "register",
        "vectors": all_vectors,
        "per_photo_count": 1 + N_AUG_PER_TEMPLATE,
        "total_vectors": len(all_vectors),
        "threshold": float(_threshold),
        "dim": len(all_vectors[0]) if all_vectors else 0,
    }