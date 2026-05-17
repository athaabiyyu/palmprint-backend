import os
import cv2
import joblib
import numpy as np
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from matplotlib.gridspec import GridSpec
from tqdm import tqdm
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.decomposition import PCA
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.metrics import (accuracy_score, confusion_matrix,
                             precision_score, recall_score, f1_score)
import warnings
warnings.filterwarnings('ignore')

try:
    from ultralytics import YOLO
    YOLO_AVAILABLE = True
except ImportError:
    print("⚠️  ultralytics tidak ditemukan. Install: pip install ultralytics")
    YOLO_AVAILABLE = False


# =====================================================================
# CONFIGURATION -TES
# =====================================================================

class Config:
    DATASET_PATH   = "dataset"
    OUTPUT_PATH    = "results"

    # YOLOv11
    YOLO_MODEL     = "yolo11n.pt"
    YOLO_CONF      = 0.25
    YOLO_CLASS_ID  = 0

    # ROI & image size
    ROI_SIZE       = 200
    IMAGE_SIZE     = 64          # ← Paper: 64×64 px (bukan 128)

    # HOG — sesuai paper: 9 bins, cell 8×8, block 2×2 cells → 49 blocks
    HOG_ORIENT     = 9
    HOG_PIXELS     = 8
    HOG_CELLS      = 2

    # SGF — Steerable Gaussian Filter, 24 orientasi (0°–345°, step 15°)
    # Paper Section 4.1.5: FR_i = cos(θ_i)*Ix + sin(θ_i)*Iy
    # Feature: mean + std setiap FR_i  →  48 nilai (24×2)
    SGF_ANGLES     = np.deg2rad(np.arange(0, 360, 15))   # 24 sudut

    # CLAHE (untuk ROI enhancement)
    CLAHE_CLIP     = 2.0
    CLAHE_TILE     = (8, 8)

    # Gabor filter (enhancement sebelum HOG-SGF)
    GABOR_KSIZE    = 21
    GABOR_SIGMA    = 4.0
    GABOR_LAMBDA   = 10.0
    GABOR_GAMMA    = 0.5
    GABOR_THETAS   = [0, np.pi/4, np.pi/2, 3*np.pi/4]

    # PCA
    PCA_COMPONENTS = 200

    # Cosine threshold — None = auto dari distribusi train similarity
    COSINE_THRESHOLD = None


os.makedirs(Config.OUTPUT_PATH, exist_ok=True)


# =====================================================================
# STEP 1 — YOLOv11 HAND DETECTION
# =====================================================================

class YOLOHandDetector:
    def __init__(self):
        self.model = None
        self.ready = False
        if not YOLO_AVAILABLE:
            return
        try:
            print(f"   [YOLO] Memuat model: {Config.YOLO_MODEL} ...")
            self.model = YOLO(Config.YOLO_MODEL)
            self.ready = True
            print("   [YOLO] Model berhasil dimuat ✓")
        except Exception as e:
            print(f"   [YOLO] Gagal: {e}  →  pakai fallback kontur.")

    def detect(self, img_bgr):
        if not self.ready:
            return None, 0.0
        results = self.model(img_bgr, conf=Config.YOLO_CONF, verbose=False)[0]
        best_bbox, best_conf = None, 0.0
        for box in results.boxes:
            if int(box.cls[0]) != Config.YOLO_CLASS_ID:
                continue
            conf = float(box.conf[0])
            if conf > best_conf:
                best_conf = conf
                best_bbox = tuple(map(int, box.xyxy[0]))
        return best_bbox, best_conf


_detector = None

def get_detector():
    global _detector
    if _detector is None:
        _detector = YOLOHandDetector()
    return _detector


# =====================================================================
# STEP 2 — ROI PALM EXTRACTION (centroid-based)
# =====================================================================

def extract_roi(img_bgr):
    """
    Centroid-based center crop:
      1. Grayscale + Gaussian blur
      2. Dual threshold (OTSU + fixed=100) → kontur terbesar
      3. Centroid momen → pusat crop
      4. Crop persegi ROI_SIZE × ROI_SIZE, padding jika perlu
    """
    h_img, w_img = img_bgr.shape[:2]
    debug = {}

    detector   = get_detector()
    bbox, conf = detector.detect(img_bgr)
    debug['bbox'] = bbox
    debug['conf'] = conf

    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)

    _, th_otsu  = cv2.threshold(blur, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    _, th_fixed = cv2.threshold(blur, 100, 255, cv2.THRESH_BINARY)

    best_contour = None
    best_area    = 0
    for th in [th_otsu, th_fixed]:
        cnts, _ = cv2.findContours(th, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        if not cnts:
            continue
        c    = max(cnts, key=cv2.contourArea)
        area = cv2.contourArea(c)
        if area > 5000 and area > best_area:
            best_area    = area
            best_contour = c

    size = Config.ROI_SIZE

    if best_contour is None:
        cx, cy = w_img // 2, h_img // 2
    else:
        M = cv2.moments(best_contour)
        if M["m00"] == 0:
            cx, cy = w_img // 2, h_img // 2
        else:
            cx = int(M["m10"] / M["m00"])
            cy = int(M["m01"] / M["m00"])

    x1 = max(cx - size // 2, 0)
    y1 = max(cy - size // 2, 0)
    x2 = min(cx + size // 2, w_img)
    y2 = min(cy + size // 2, h_img)

    roi = gray[y1:y2, x1:x2]

    if roi.shape[0] < size or roi.shape[1] < size:
        pad = np.zeros((size, size), dtype=np.uint8)
        yo  = (size - roi.shape[0]) // 2
        xo  = (size - roi.shape[1]) // 2
        pad[yo:yo+roi.shape[0], xo:xo+roi.shape[1]] = roi
        roi = pad

    debug['cx']       = cx
    debug['cy']       = cy
    debug['roi_rect'] = (x1, y1, x2, y2)

    return roi, debug


# =====================================================================
# STEP 3 — IMAGE ENHANCEMENT (Gabor + CLAHE)
# =====================================================================

def enhance_gabor(img_gray):
    """
    Gabor filter bank (multi-orientasi) + CLAHE.
    Menonjolkan garis palmprint, lalu meratakan kontras.
    """
    responses = []
    for theta in Config.GABOR_THETAS:
        kernel = cv2.getGaborKernel(
            ksize  = (Config.GABOR_KSIZE, Config.GABOR_KSIZE),
            sigma  = Config.GABOR_SIGMA,
            theta  = theta,
            lambd  = Config.GABOR_LAMBDA,
            gamma  = Config.GABOR_GAMMA,
            psi    = 0,
            ktype  = cv2.CV_32F
        )
        resp = cv2.filter2D(img_gray.astype(np.float32), cv2.CV_32F, kernel)
        responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)
    gabor_max = cv2.normalize(gabor_max, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)

    clahe = cv2.createCLAHE(clipLimit=Config.CLAHE_CLIP, tileGridSize=Config.CLAHE_TILE)
    return clahe.apply(gabor_max)


# =====================================================================
# STEP 4 — HOG-SGF FEATURE EXTRACTION  (implementasi Paper)
# =====================================================================

from skimage.feature import hog


def extract_hog_features(img_64):
    """
    HOG sesuai paper:
      - Image 64×64
      - Cell 8×8, Block 2×2 cells (16×16 px), 49 overlapping blocks
      - 9 orientation bins
      - Total: 49 × 4 cells × 9 bins = 1764 dim
    """
    feat = hog(
        img_64,
        orientations    = Config.HOG_ORIENT,
        pixels_per_cell = (Config.HOG_PIXELS, Config.HOG_PIXELS),
        cells_per_block = (Config.HOG_CELLS,  Config.HOG_CELLS),
        block_norm      = 'L2',
        visualize       = False
    )
    return feat


def extract_sgf_features(img_64):
    """
    Steerable Gaussian Filter (SGF) sesuai Section 4.1.5–4.1.6 paper:
      FR_i = cos(θ_i)*Ix + sin(θ_i)*Iy
      Fitur: μ_i = mean(FR_i),  σ_i = std(FR_i)
      24 sudut × 2 statistik = 48 dim

    Ix, Iy dihitung dari Sobel (approx. gradient Gaussian).
    """
    img_f = img_64.astype(np.float32)

    # Gradien gambar (paper: partial derivatives Gaussian → approx. Sobel)
    Ix = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)

    sgf_feats = []
    for theta in Config.SGF_ANGLES:
        # Filter response pada orientasi θ
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        sgf_feats.append(np.mean(FR))   # μ_i
        sgf_feats.append(np.std(FR))    # σ_i

    return np.array(sgf_feats, dtype=np.float32)  # 48 dim


def normalize_euclidean(feat):
    """
    Euclidean (L2) normalization sesuai Eq. 27 paper:
      NF = F / sqrt(sum(F_i^2))
    """
    norm = np.linalg.norm(feat)
    if norm == 0:
        return feat
    return feat / norm


def extract_hog_sgf(img_gray, visualize=False):
    """
    Pipeline HOG-SGF lengkap sesuai paper:
      1. Resize ke 64×64 (paper: IMAGE_SIZE = 64)
      2. Ekstrak HOG (1764 dim)
      3. Ekstrak SGF mean+std (48 dim)
      4. Gabung: F_HOG-SGF = F_HOG ∪ F_SGF  (1812 dim)
      5. Normalisasi Euclidean
    Jika visualize=True, kembalikan juga HOG image untuk visualisasi.
    """
    img_64 = cv2.resize(img_gray, (Config.IMAGE_SIZE, Config.IMAGE_SIZE))

    # HOG
    if visualize:
        hog_feat, hog_img = hog(
            img_64,
            orientations    = Config.HOG_ORIENT,
            pixels_per_cell = (Config.HOG_PIXELS, Config.HOG_PIXELS),
            cells_per_block = (Config.HOG_CELLS,  Config.HOG_CELLS),
            block_norm      = 'L2',
            visualize       = True
        )
    else:
        hog_feat = extract_hog_features(img_64)
        hog_img  = None

    # SGF
    sgf_feat = extract_sgf_features(img_64)

    # Gabung & normalisasi
    combined = np.concatenate([hog_feat, sgf_feat])
    combined = normalize_euclidean(combined)

    if visualize:
        return combined, hog_img, img_64
    return combined


def extract_feature(img_gray):
    """Entry point untuk pipeline utama."""
    return extract_hog_sgf(img_gray, visualize=False)


# =====================================================================
# LABEL PARSING
# =====================================================================

def parse_label(fname):
    """
    Ekstrak subject ID dari nama file.
    Format: subject_282_left_hand_08.tiff
      → ambil bagian PERTAMA yang murni digit → '282'
    """
    name  = os.path.splitext(fname)[0]
    parts = name.split("_")
    for part in parts:
        if part.isdigit():
            return part
    return parts[0]


# =====================================================================
# THRESHOLD OTOMATIS
# =====================================================================

def compute_auto_threshold(X_train_pca, y_train, percentile=10):
    """
    Hitung threshold dari distribusi intra-class cosine similarity.
    Ambil percentile ke-10 sebagai threshold minimum.
    """
    if Config.COSINE_THRESHOLD is not None:
        return Config.COSINE_THRESHOLD

    print("   [AUTO-THRESHOLD] Menghitung dari distribusi intra-class similarity...")
    sims   = cosine_similarity(X_train_pca)
    labels = np.array(y_train)
    intra  = []
    for i in range(len(labels)):
        same = np.where(labels == labels[i])[0]
        same = same[same != i]
        if len(same) > 0:
            intra.append(np.mean(sims[i, same]))

    threshold = float(np.percentile(intra, percentile))
    print(f"   Intra-class sim: mean={np.mean(intra):.4f}, "
          f"min={np.min(intra):.4f}, p10={threshold:.4f}")
    print(f"   Threshold otomatis: {threshold:.4f}")
    return threshold


# =====================================================================
# STEP 5 — COSINE SIMILARITY MATCHING
# =====================================================================

def cosine_classifier(X_train, y_train, X_test, threshold):
    sims   = cosine_similarity(X_test, X_train)
    preds, scores = [], []
    for s in sims:
        idx     = np.argmax(s)
        max_sim = s[idx]
        preds.append(y_train[idx] if max_sim >= threshold else "UNKNOWN")
        scores.append(max_sim)
    return np.array(preds), np.array(scores)


# =====================================================================
# STEP 6 — EVALUATION
# =====================================================================

def evaluate_model(y_true, y_pred, threshold):
    """
    Dua mode evaluasi:
    - STRICT : UNKNOWN dihitung sebagai salah
    - VALID  : hanya sample yang diterima
    """
    valid = y_pred != "UNKNOWN"

    y_pred_strict = np.where(valid, y_pred, "__WRONG__")
    acc_strict    = accuracy_score(y_true, y_pred_strict)

    if valid.sum() == 0:
        acc_v = prec_v = rec_v = f1_v = 0.0
    else:
        y_tv, y_pv = y_true[valid], y_pred[valid]
        acc_v  = accuracy_score(y_tv, y_pv)
        prec_v = precision_score(y_tv, y_pv, average='weighted', zero_division=0)
        rec_v  = recall_score(y_tv, y_pv, average='weighted', zero_division=0)
        f1_v   = f1_score(y_tv, y_pv, average='weighted', zero_division=0)

    rr = (~valid).mean()

    print("\n" + "="*60)
    print("  EVALUATION — IDENTITY VERIFICATION")
    print("="*60)
    print(f"  Threshold        : {threshold:.4f}")
    print(f"  Total test       : {len(y_true)}")
    print(f"  Accepted         : {valid.sum()} ({valid.mean()*100:.1f}%)")
    print(f"  Rejected (UNKN)  : {(~valid).sum()} ({rr*100:.1f}%)")
    print()
    print(f"  [STRICT]  Accuracy : {acc_strict:.4f}  ({acc_strict*100:.2f}%)")
    print()
    print(f"  [VALID]   Accuracy  : {acc_v:.4f}  ({acc_v*100:.2f}%)")
    print(f"  [VALID]   Precision : {prec_v:.4f}")
    print(f"  [VALID]   Recall    : {rec_v:.4f}")
    print(f"  [VALID]   F1-Score  : {f1_v:.4f}")
    print("="*60)

    if rr > 0.20:
        print(f"\n  ⚠  Rejection rate {rr*100:.1f}% terlalu tinggi.")
        print(f"     Coba turunkan COSINE_THRESHOLD atau biarkan None (auto).")
    if acc_strict < 0.5 and acc_v > 0.9:
        print(f"\n  ⚠  Strict acc rendah ({acc_strict*100:.1f}%) tapi valid acc tinggi.")
        print(f"     → Threshold terlalu ketat, banyak sample benar dibuang.")

    return dict(
        threshold      = threshold,
        acc_strict     = acc_strict,
        acc_valid      = acc_v,
        precision      = prec_v,
        recall         = rec_v,
        f1             = f1_v,
        rejection_rate = rr
    )


# =====================================================================
# DATASET LOADER
# =====================================================================

def load_dataset():
    features, labels = [], []
    exts  = ('.png', '.jpg', '.jpeg', '.bmp', '.tiff', '.tif')
    files = [f for f in os.listdir(Config.DATASET_PATH) if f.lower().endswith(exts)]

    print("\n  [DIAGNOSIS] Sample label parsing:")
    for f in files[:6]:
        print(f"    {f:<50} label: '{parse_label(f)}'")
    sample_labels = [parse_label(f) for f in files]
    n_unique = len(set(sample_labels))
    print(f"  Unique labels detected: {n_unique}")
    if n_unique < 10:
        print("  ⚠  Terlalu sedikit label unik — periksa fungsi parse_label!")
    print()

    for fname in tqdm(files, desc="Loading & HOG-SGF"):
        img = cv2.imread(os.path.join(Config.DATASET_PATH, fname))
        if img is None:
            continue

        label  = parse_label(fname)
        roi, _ = extract_roi(img)
        roi    = enhance_gabor(roi)          # Gabor+CLAHE enhancement
        feat   = extract_feature(roi)        # HOG-SGF (1812 dim)

        features.append(feat)
        labels.append(label)

    features = np.array(features)
    labels   = np.array(labels)

    hog_dim = 49 * 4 * Config.HOG_ORIENT    # 1764
    sgf_dim = len(Config.SGF_ANGLES) * 2    # 48
    print(f"\n✓ {len(features)} images | {len(np.unique(labels))} subjects")
    print(f"  Feature dim: HOG={hog_dim} + SGF={sgf_dim} = {hog_dim+sgf_dim} (+ L2-norm)")
    print(f"  Actual dim : {features.shape[1]}")
    return features, labels


# =====================================================================
# VISUALIZATIONS
# =====================================================================

def visualize_pipeline(img_bgr, sample_name="Sample"):
    detector   = get_detector()
    bbox, conf = detector.detect(img_bgr)
    roi_raw, dbg = extract_roi(img_bgr)
    enhanced     = enhance_gabor(roi_raw)

    # HOG-SGF visualization
    hog_sgf_feat, hog_img, img_64 = extract_hog_sgf(enhanced, visualize=True)

    # SGF responses untuk visualisasi (pilih 4 sudut representatif)
    img_f = img_64.astype(np.float32)
    Ix = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)
    sgf_vis = []
    for theta in [0, np.pi/4, np.pi/2, 3*np.pi/4]:
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        FR_norm = cv2.normalize(np.abs(FR), None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)
        sgf_vis.append(FR_norm)

    cx       = dbg.get('cx', img_bgr.shape[1]//2)
    cy       = dbg.get('cy', img_bgr.shape[0]//2)
    roi_rect = dbg.get('roi_rect')

    img_ann = img_bgr.copy()
    if bbox:
        cv2.rectangle(img_ann, bbox[:2], bbox[2:], (0, 255, 0), 2)
        cv2.putText(img_ann, f"Hand {conf:.2f}", (bbox[0], max(bbox[1]-8, 0)),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)
    cv2.circle(img_ann, (cx, cy), 6, (0, 165, 255), -1)
    if roi_rect:
        rx, ry, rx2, ry2 = roi_rect
        cv2.rectangle(img_ann, (rx, ry), (rx2, ry2), (0, 165, 255), 3)

    # ── Layout: 4 baris × 4 kolom ──
    fig = plt.figure(figsize=(20, 16))
    gs  = GridSpec(4, 4, figure=fig, hspace=0.50, wspace=0.30)

    # Row 0
    ax = fig.add_subplot(gs[0, 0])
    ax.imshow(cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB))
    ax.set_title("1. Input Image", fontsize=11, fontweight='bold'); ax.axis("off")

    ax = fig.add_subplot(gs[0, 1])
    ax.imshow(cv2.cvtColor(img_ann, cv2.COLOR_BGR2RGB))
    ax.set_title("2. YOLOv11 + Centroid ROI", fontsize=11, fontweight='bold'); ax.axis("off")

    ax = fig.add_subplot(gs[0, 2])
    ax.imshow(roi_raw, cmap='gray')
    ax.set_title(f"3. ROI ({Config.ROI_SIZE}×{Config.ROI_SIZE})", fontsize=11, fontweight='bold')
    ax.axis("off")

    ax = fig.add_subplot(gs[0, 3])
    ax.imshow(enhanced, cmap='gray')
    ax.set_title("4. Gabor+CLAHE Enhanced", fontsize=11, fontweight='bold'); ax.axis("off")

    # Row 1 — SGF responses (4 orientasi)
    angle_labels = ["0°", "45°", "90°", "135°"]
    for i, (fr_img, lbl) in enumerate(zip(sgf_vis, angle_labels)):
        ax = fig.add_subplot(gs[1, i])
        ax.imshow(fr_img, cmap='hot')
        ax.set_title(f"SGF θ={lbl}", fontsize=10, fontweight='bold'); ax.axis("off")

    # Row 2
    ax = fig.add_subplot(gs[2, 0])
    ax.imshow(img_64, cmap='gray')
    ax.set_title(f"5. Resize {Config.IMAGE_SIZE}×{Config.IMAGE_SIZE}", fontsize=11, fontweight='bold')
    ax.axis("off")

    ax = fig.add_subplot(gs[2, 1])
    ax.imshow(hog_img, cmap='gray')
    ax.set_title(f"6. HOG Features\n(9 bins, 8×8 cell, 2×2 block)", fontsize=11, fontweight='bold')
    ax.axis("off")

    ax = fig.add_subplot(gs[2, 2])
    hog_dim = 49 * 4 * Config.HOG_ORIENT
    sgf_dim = len(Config.SGF_ANGLES) * 2
    ax.plot(hog_sgf_feat[:hog_dim], lw=0.8, color='steelblue', label=f'HOG ({hog_dim}d)')
    ax.plot(range(hog_dim, hog_dim+sgf_dim),
            hog_sgf_feat[hog_dim:], lw=1.5, color='darkorange', label=f'SGF ({sgf_dim}d)')
    ax.set_title(f"7. HOG-SGF Vector ({len(hog_sgf_feat)}d)", fontsize=11, fontweight='bold')
    ax.set_xlabel("Feature Index"); ax.legend(fontsize=8); ax.grid(True, alpha=0.3)

    # Pipeline overview box
    ax = fig.add_subplot(gs[2, 3]); ax.axis("off")
    steps = [
        ("1. Input Image",            "#AED6F1"),
        ("2. YOLOv11 Detection",      "#A9DFBF"),
        ("3. Centroid ROI Crop",      "#A9DFBF"),
        ("4. Gabor + CLAHE",          "#FAD7A0"),
        ("5. HOG Extraction (1764d)", "#FAD7A0"),
        ("6. SGF mean+std (48d)",     "#FAD7A0"),
        ("7. L2 Norm → 1812d",        "#F0B27A"),
        ("8. PCA Reduction",          "#D7BDE2"),
        ("9. Cosine Similarity",      "#D7BDE2"),
        ("10. Identity Verification", "#F1948A"),
    ]
    n_s   = len(steps)
    box_h = 0.087
    gap   = (1.0 - n_s * box_h) / (n_s + 1)
    for i, (txt, color) in enumerate(steps):
        y = 1.0 - gap*(i+1) - box_h*(i+1)
        rect = patches.FancyBboxPatch(
            (0.03, y), 0.94, box_h, boxstyle="round,pad=0.01",
            facecolor=color, edgecolor='#555', linewidth=1.2,
            transform=ax.transAxes, clip_on=False)
        ax.add_patch(rect)
        ax.text(0.5, y + box_h/2, txt, transform=ax.transAxes,
                fontsize=7.5, ha='center', va='center', fontweight='bold')
    ax.set_title("Pipeline (HOG-SGF)", fontsize=11, fontweight='bold')

    # Row 3 — SGF feature bar & info
    ax = fig.add_subplot(gs[3, 0:2])
    sgf_only = hog_sgf_feat[hog_dim:]
    means = sgf_only[0::2]
    stds  = sgf_only[1::2]
    x = np.arange(len(Config.SGF_ANGLES))
    ax.bar(x - 0.2, means, 0.4, label='μ (mean)', color='darkorange', alpha=0.75)
    ax.bar(x + 0.2, stds,  0.4, label='σ (std)',  color='steelblue',  alpha=0.75)
    ax.set_xticks(x[::4])
    ax.set_xticklabels([f"{int(np.rad2deg(Config.SGF_ANGLES[i]))}°" for i in x[::4]], fontsize=8)
    ax.set_title("SGF Features: mean & std per orientasi (24 sudut)", fontsize=11, fontweight='bold')
    ax.legend(); ax.grid(True, alpha=0.3, axis='y')

    ax = fig.add_subplot(gs[3, 2:4]); ax.axis("off")
    hog_dim_actual = 49 * 4 * Config.HOG_ORIENT
    sgf_dim_actual = len(Config.SGF_ANGLES) * 2
    info = (
        f"  HOG-SGF (Paper Implementation)\n"
        f"  {'─'*36}\n\n"
        f"  Image size    : {Config.IMAGE_SIZE}×{Config.IMAGE_SIZE} px\n"
        f"  HOG:\n"
        f"    Cell        : {Config.HOG_PIXELS}×{Config.HOG_PIXELS} px\n"
        f"    Block       : {Config.HOG_CELLS}×{Config.HOG_CELLS} cells\n"
        f"    Blocks/img  : 7×7 = 49\n"
        f"    Bins        : {Config.HOG_ORIENT}\n"
        f"    HOG dim     : 49×4×9 = {hog_dim_actual}\n\n"
        f"  SGF:\n"
        f"    Angles      : 24 (0°–345°, step 15°)\n"
        f"    Features    : mean + std per FR\n"
        f"    SGF dim     : 24×2 = {sgf_dim_actual}\n\n"
        f"  Total (L2-norm): {hog_dim_actual+sgf_dim_actual} dim\n"
        f"  (Paper: 1812 dim)\n"
    )
    ax.text(0.03, 0.97, info, transform=ax.transAxes, fontsize=9,
            va='top', fontfamily='monospace',
            bbox=dict(boxstyle='round', facecolor='#f5f5f5', alpha=0.85))

    fig.suptitle(f"PALMPRINT PIPELINE — HOG-SGF (Paper-based): {sample_name}",
                 fontsize=13, fontweight='bold', y=1.0)
    plt.tight_layout()
    return fig


def visualize_pca_analysis(X_train_scaled, X_train_pca, pca):
    fig    = plt.figure(figsize=(16, 10))
    cumsum = np.cumsum(pca.explained_variance_ratio_)
    tv     = np.sum(pca.explained_variance_ratio_)

    ax1 = fig.add_subplot(2, 3, 1)
    ax1.plot(range(1, len(cumsum)+1), cumsum, 'b-', lw=2, marker='o', ms=4)
    ax1.axhline(0.95, color='r', ls='--', lw=2, label='95%')
    ax1.fill_between(range(1, len(cumsum)+1), cumsum, alpha=0.3)
    ax1.set_xlabel("Components"); ax1.set_ylabel("Cumulative Variance")
    ax1.set_title("Cumulative Explained Variance", fontsize=11, fontweight='bold')
    ax1.legend(); ax1.grid(True, alpha=0.3); ax1.set_xlim(0, 300)

    ax2 = fig.add_subplot(2, 3, 2)
    ax2.bar(range(1, 31), pca.explained_variance_ratio_[:30],
            color='steelblue', edgecolor='navy')
    ax2.set_xlabel("Principal Component"); ax2.set_ylabel("Explained Variance Ratio")
    ax2.set_title("Top 30 Components", fontsize=11, fontweight='bold')
    ax2.grid(True, alpha=0.3, axis='y')

    ax3 = fig.add_subplot(2, 3, 3)
    ax3.pie([tv, 1-tv], labels=[f'Retained\n{tv:.2%}', f'Lost\n{1-tv:.2%}'],
            colors=['#2ecc71', '#e74c3c'], autopct='%1.1f%%', startangle=90)
    ax3.set_title(f"Info Retention ({Config.PCA_COMPONENTS} comp)", fontsize=11, fontweight='bold')

    n   = min(500, len(X_train_pca))
    ax4 = fig.add_subplot(2, 3, 4)
    sc  = ax4.scatter(X_train_pca[:n, 0], X_train_pca[:n, 1],
                      c=range(n), cmap='viridis', s=30, alpha=0.6)
    ax4.set_xlabel(f"PC1 ({pca.explained_variance_ratio_[0]:.2%})")
    ax4.set_ylabel(f"PC2 ({pca.explained_variance_ratio_[1]:.2%})")
    ax4.set_title("2D PCA Projection", fontsize=11, fontweight='bold')
    ax4.grid(True, alpha=0.3); plt.colorbar(sc, ax=ax4)

    ax5 = fig.add_subplot(2, 3, 5, projection='3d')
    ax5.scatter(X_train_pca[:n, 0], X_train_pca[:n, 1], X_train_pca[:n, 2],
                c=range(n), cmap='viridis', s=30, alpha=0.6)
    ax5.set_xlabel("PC1"); ax5.set_ylabel("PC2"); ax5.set_zlabel("PC3")
    ax5.set_title("3D PCA Projection", fontsize=11, fontweight='bold')

    ax6 = fig.add_subplot(2, 3, 6); ax6.axis("off")
    ax6.text(0.05, 0.95,
             f"  PCA SUMMARY\n  {'─'*30}\n\n"
             f"  Original dim : {X_train_scaled.shape[1]:,}\n"
             f"  Reduced dim  : {Config.PCA_COMPONENTS}\n"
             f"  Compression  : {(1-Config.PCA_COMPONENTS/X_train_scaled.shape[1])*100:.1f}%\n\n"
             f"  Total var    : {tv:.2%}\n"
             f"  Info loss    : {(1-tv)*100:.2f}%\n\n"
             f"  95% var at   : {np.argmax(cumsum >= 0.95)+1} comp\n"
             f"  99% var at   : {np.argmax(cumsum >= 0.99)+1} comp\n",
             transform=ax6.transAxes, fontsize=10, va='top', fontfamily='monospace',
             bbox=dict(boxstyle='round', facecolor='wheat', alpha=0.5))

    fig.suptitle("PCA DIMENSIONALITY REDUCTION ANALYSIS", fontsize=13, fontweight='bold')
    plt.tight_layout()
    return fig


def visualize_classification_results(y_test, y_pred, scores, threshold):
    fig = plt.figure(figsize=(16, 10))

    valid         = y_pred != "UNKNOWN"
    y_tv, y_pv    = y_test[valid], y_pred[valid]
    acc_v         = accuracy_score(y_tv, y_pv) if valid.sum() > 0 else 0
    prec_v        = precision_score(y_tv, y_pv, average='weighted', zero_division=0)
    rec_v         = recall_score(y_tv, y_pv, average='weighted', zero_division=0)
    f1_v          = f1_score(y_tv, y_pv, average='weighted', zero_division=0)
    y_pred_strict = np.where(valid, y_pred, "__WRONG__")
    acc_strict    = accuracy_score(y_test, y_pred_strict)
    acc_s = scores[valid]
    rej_s = scores[~valid]

    ax1 = fig.add_subplot(2, 3, 1)
    if len(acc_s) > 0:
        ax1.hist(acc_s, bins=30, alpha=0.7, color='green', edgecolor='darkgreen',
                 label=f'Accepted (n={valid.sum()})')
    if len(rej_s) > 0:
        ax1.hist(rej_s, bins=30, alpha=0.7, color='red', edgecolor='darkred',
                 label=f'Rejected (n={(~valid).sum()})')
    ax1.axvline(threshold, color='black', ls='--', lw=2, label=f'Thr={threshold:.3f}')
    ax1.set_xlabel("Cosine Similarity"); ax1.set_ylabel("Frequency")
    ax1.set_title("Score Distribution", fontsize=11, fontweight='bold')
    ax1.legend(); ax1.grid(True, alpha=0.3, axis='y')

    unique_labels = np.unique(y_test)[:20]
    ax2 = fig.add_subplot(2, 3, 2)
    pca_cls = [accuracy_score(y_test[y_test==l], y_pred[y_test==l]) for l in unique_labels]
    clrs    = ['green' if a==1.0 else 'orange' if a>=0.8 else 'red' for a in pca_cls]
    ax2.barh(range(len(unique_labels)), pca_cls, color=clrs, edgecolor='black', alpha=0.7)
    ax2.set_yticks(range(len(unique_labels)))
    ax2.set_yticklabels(unique_labels, fontsize=8)
    ax2.set_xlabel("Accuracy (UNKNOWN=wrong)")
    ax2.set_title("Per-Class Accuracy (First 20)", fontsize=11, fontweight='bold')
    ax2.set_xlim(0, 1.05); ax2.grid(True, alpha=0.3, axis='x')

    ax3 = fig.add_subplot(2, 3, 3); ax3.axis("off")
    ax3.text(0.05, 0.95,
             f"  CLASSIFICATION STATS\n  {'─'*28}\n\n"
             f"  Method   : HOG-SGF + PCA\n"
             f"             + Cosine Sim\n\n"
             f"  Total    : {len(y_test)}\n"
             f"  Accepted : {valid.sum()} ({valid.mean()*100:.1f}%)\n"
             f"  Rejected : {(~valid).sum()} ({(~valid).mean()*100:.1f}%)\n\n"
             f"  [STRICT]  Acc: {acc_strict:.4f} ({acc_strict*100:.1f}%)\n\n"
             f"  [VALID]   Acc : {acc_v:.4f} ({acc_v*100:.1f}%)\n"
             f"            Prec: {prec_v:.4f}\n"
             f"            Rec : {rec_v:.4f}\n"
             f"            F1  : {f1_v:.4f}\n\n"
             f"  Threshold: {threshold:.4f}\n",
             transform=ax3.transAxes, fontsize=9, va='top', fontfamily='monospace',
             bbox=dict(boxstyle='round', facecolor='lightblue', alpha=0.5))

    ax4 = fig.add_subplot(2, 3, 4)
    if len(y_tv) > 0:
        cm = confusion_matrix(y_tv, y_pv, labels=unique_labels[:10])
        im = ax4.imshow(cm[:10, :10], cmap='Blues', aspect='auto')
        ax4.set_xticks(range(min(10, len(unique_labels))))
        ax4.set_yticks(range(min(10, len(unique_labels))))
        ax4.set_xticklabels(unique_labels[:10], fontsize=7, rotation=45)
        ax4.set_yticklabels(unique_labels[:10], fontsize=7)
        plt.colorbar(im, ax=ax4)
    ax4.set_xlabel("Predicted"); ax4.set_ylabel("True")
    ax4.set_title("Confusion Matrix (10×10, accepted)", fontsize=11, fontweight='bold')

    ax5 = fig.add_subplot(2, 3, 5)
    thresholds = np.arange(0.05, 1.0, 0.05)
    accs_strict, accs_valid, rejs = [], [], []
    for th in thresholds:
        tmp   = np.where(scores >= th, y_pred, "UNKNOWN")
        vm    = tmp != "UNKNOWN"
        tmp_s = np.where(vm, tmp, "__WRONG__")
        accs_strict.append(accuracy_score(y_test, tmp_s))
        accs_valid.append(accuracy_score(y_test[vm], tmp[vm]) if vm.sum() > 0 else 0)
        rejs.append((~vm).mean())
    ax5b = ax5.twinx()
    ax5.plot(thresholds, accs_strict, 'b-o', lw=2, ms=4, label='Acc strict')
    ax5.plot(thresholds, accs_valid,  'g--s', lw=2, ms=4, label='Acc valid-only')
    ax5b.plot(thresholds, rejs, 'r-^', lw=2, ms=4, label='Reject rate')
    ax5.axvline(threshold, color='orange', ls='--', lw=2, label=f'Current={threshold:.3f}')
    ax5.set_xlabel("Threshold"); ax5.set_ylabel("Accuracy", color='b')
    ax5b.set_ylabel("Rejection Rate", color='r')
    ax5.set_title("Threshold Trade-off", fontsize=11, fontweight='bold')
    ax5.tick_params(axis='y', labelcolor='b')
    ax5b.tick_params(axis='y', labelcolor='r')
    ax5.legend(loc='lower left', fontsize=7)
    ax5b.legend(loc='upper right', fontsize=7)
    ax5.grid(True, alpha=0.3)

    ax6 = fig.add_subplot(2, 3, 6); ax6.axis("off")
    ax6.text(0.05, 0.95,
             f"  PERFORMANCE SUMMARY\n  {'─'*28}\n\n"
             f"  Strict acc : {acc_strict:.4f} ({acc_strict*100:.2f}%)\n"
             f"  Valid  acc : {acc_v:.4f} ({acc_v*100:.2f}%)\n"
             f"  Precision  : {prec_v:.4f}\n"
             f"  Recall     : {rec_v:.4f}\n"
             f"  F1-Score   : {f1_v:.4f}\n\n"
             f"  Rej. Rate  : {(~valid).mean()*100:.2f}%\n"
             f"  Accepted   : {valid.sum()}/{len(y_test)}\n",
             transform=ax6.transAxes, fontsize=9, va='top', fontfamily='monospace',
             bbox=dict(boxstyle='round', facecolor='lightgreen', alpha=0.5))

    fig.suptitle("CLASSIFICATION RESULTS — HOG-SGF + PCA + Cosine Similarity",
                 fontsize=13, fontweight='bold')
    plt.tight_layout()
    return fig


# =====================================================================
# MAIN
# =====================================================================

def main():
    print("\n" + "="*70)
    print("  PALMPRINT RECOGNITION")
    print("  YOLOv11 + Centroid ROI + Gabor + HOG-SGF (Paper) + PCA + Cosine")
    print("="*70)
    print(f"\n  Feature method  : HOG-SGF  (Gumaei et al., Sensors 2018)")
    print(f"  HOG dim         : 49 blocks × 4 cells × 9 bins = {49*4*9}")
    print(f"  SGF dim         : 24 angles × 2 stats (μ,σ) = {24*2}")
    print(f"  Combined + L2   : {49*4*9 + 24*2} dim")
    print(f"  PCA components  : {Config.PCA_COMPONENTS}")

    exts    = ('.tiff', '.tif', '.png', '.jpg', '.jpeg', '.bmp')
    samples = [f for f in os.listdir(Config.DATASET_PATH) if f.lower().endswith(exts)]
    if not samples:
        print("  Folder 'dataset' kosong!"); return

    # 1. Pipeline visualization
    print("\n[1] Visualizing pipeline sample...")
    img = cv2.imread(os.path.join(Config.DATASET_PATH, samples[0]))
    if img is None:
        print("  Gagal membaca sample."); return
    fig1 = visualize_pipeline(img, samples[0])
    out1 = os.path.join(Config.OUTPUT_PATH, '01_pipeline_hog_sgf.png')
    plt.savefig(out1, dpi=150, bbox_inches='tight')
    print(f"   Saved: {out1}"); plt.close()

    # 2. Load dataset
    print("\n[2] Loading dataset (HOG-SGF features)...")
    X, y = load_dataset()
    if len(X) == 0:
        print("  Dataset kosong!"); return

    # 3. Train/test split
    print("\n[3] Train-test split (70/30, stratified)...")
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.3, stratify=y, random_state=42)
    print(f"   Train: {len(X_train)}  |  Test: {len(X_test)}")

    # 4. Normalization
    print("\n[4] StandardScaler normalization...")
    scaler     = StandardScaler()
    X_train_sc = scaler.fit_transform(X_train)
    X_test_sc  = scaler.transform(X_test)

    # 5. PCA (whiten=True agar cosine similarity lebih diskriminatif)
    print(f"\n[5] PCA (n={Config.PCA_COMPONENTS}, whiten=True)...")
    pca         = PCA(n_components=Config.PCA_COMPONENTS, whiten=True, random_state=42)
    X_train_pca = pca.fit_transform(X_train_sc)
    X_test_pca  = pca.transform(X_test_sc)
    print(f"   Var retained : {np.sum(pca.explained_variance_ratio_):.4f}")
    print(f"   Dim          : {X_train.shape[1]} → {Config.PCA_COMPONENTS}")
    
    # ── EXPORT MODEL ──────────────────────────────────────────
    print("\n[5b] Menyimpan model Scaler & PCA...")
    
    models_dir = "models"
    os.makedirs(models_dir, exist_ok=True)
    
    # Simpan scaler
    joblib.dump(scaler, os.path.join(models_dir, "scaler.pkl"))
    print(f"   Saved: {models_dir}/scaler.pkl")
    
    # Simpan PCA
    joblib.dump(pca, os.path.join(models_dir, "pca.pkl"))
    print(f"   Saved: {models_dir}/pca.pkl")
    
    print("   ✓ Model berhasil disimpan!")
    # ──────────────────────────────────────────────────────────

    fig3 = visualize_pca_analysis(X_train_sc, X_train_pca, pca)
    out3 = os.path.join(Config.OUTPUT_PATH, '02_pca_analysis.png')
    plt.savefig(out3, dpi=150, bbox_inches='tight')
    print(f"   Saved: {out3}"); plt.close()

    # 6. Auto threshold
    print(f"\n[6] Computing threshold...")
    threshold = compute_auto_threshold(X_train_pca, y_train, percentile=10)
    print(f"   Threshold used: {threshold:.4f}")
    
    # Simpan threshold setelah dihitung
    joblib.dump(threshold, os.path.join(models_dir, "threshold.pkl"))
    print(f"   Saved: {models_dir}/threshold.pkl (value: {threshold:.4f})")

    # 7. Cosine similarity matching
    print(f"\n[7] Cosine Similarity Matching...")
    y_pred, scores = cosine_classifier(X_train_pca, y_train, X_test_pca, threshold)

    # 8. Evaluation
    print("\n[8] Identity Verification — Evaluation...")
    metrics = evaluate_model(y_test, y_pred, threshold)

    fig4 = visualize_classification_results(y_test, y_pred, scores, threshold)
    out4 = os.path.join(Config.OUTPUT_PATH, '03_classification_results.png')
    plt.savefig(out4, dpi=150, bbox_inches='tight')
    print(f"   Saved: {out4}"); plt.close()

    print("\n" + "="*70)
    print(f"  Done! Output: {os.path.abspath(Config.OUTPUT_PATH)}/")
    print("="*70)
    return metrics


if __name__ == "__main__":
    main()