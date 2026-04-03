"""
palmprint_api.py
Dipanggil oleh Laravel via shell_exec:
  python palmprint_api.py --image path/to/foto.jpg
Output: JSON ke stdout
"""

import os
import sys
import json
import argparse
import warnings
warnings.filterwarnings('ignore')

import cv2
import numpy as np
import joblib
from skimage.feature import hog

# =====================================================================
# CONFIG — harus sama persis dengan main.py
# =====================================================================

BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(BASE_DIR, "models")
YOLO_MODEL = os.path.join(BASE_DIR, "yolo11n.pt")

YOLO_CONF     = 0.25
YOLO_CLASS_ID = 0
ROI_SIZE      = 200
IMAGE_SIZE    = 64
HOG_ORIENT    = 9
HOG_PIXELS    = 8
HOG_CELLS     = 2
SGF_ANGLES    = np.deg2rad(np.arange(0, 360, 15))
CLAHE_CLIP    = 2.0
CLAHE_TILE    = (8, 8)
GABOR_KSIZE   = 21
GABOR_SIGMA   = 4.0
GABOR_LAMBDA  = 10.0
GABOR_GAMMA   = 0.5
GABOR_THETAS  = [0, np.pi/4, np.pi/2, 3*np.pi/4]


# =====================================================================
# OUTPUT HELPERS — taruh di atas agar bisa dipanggil kapan saja
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
# YOLO DETECTOR — sama dengan main.py
# =====================================================================

_detector = None

def get_detector():
    global _detector
    if _detector is not None:
        return _detector
    try:
        from ultralytics import YOLO
        _detector = YOLO(YOLO_MODEL)
        return _detector
    except Exception as e:
        # Kalau YOLO gagal load, tetap lanjut pakai fallback kontur
        _detector = None
        return None


# =====================================================================
# STEP 1 — ROI EXTRACTION (sama persis dengan main.py)
# =====================================================================

def extract_roi(img_bgr):
    h_img, w_img = img_bgr.shape[:2]

    # Coba deteksi dengan YOLO dulu
    detector = get_detector()
    if detector is not None:
        try:
            results   = detector(img_bgr, conf=YOLO_CONF, verbose=False)[0]
            best_bbox = None
            best_conf = 0.0
            for box in results.boxes:
                if int(box.cls[0]) != YOLO_CLASS_ID:
                    continue
                conf = float(box.conf[0])
                if conf > best_conf:
                    best_conf = conf
                    best_bbox = tuple(map(int, box.xyxy[0]))
        except Exception:
            best_bbox = None

    # Grayscale + blur untuk kontur
    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)

    # Dual threshold → cari kontur terbesar (fallback)
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

    # Hitung centroid untuk ROI center crop
    size = ROI_SIZE
    if best_contour is None:
        cx, cy = w_img // 2, h_img // 2
    else:
        M = cv2.moments(best_contour)
        if M["m00"] == 0:
            cx, cy = w_img // 2, h_img // 2
        else:
            cx = int(M["m10"] / M["m00"])
            cy = int(M["m01"] / M["m00"])

    # ROI center crop
    x1 = max(cx - size // 2, 0)
    y1 = max(cy - size // 2, 0)
    x2 = min(cx + size // 2, w_img)
    y2 = min(cy + size // 2, h_img)

    roi = gray[y1:y2, x1:x2]

    # Padding kalau ROI terlalu kecil
    if roi.shape[0] < size or roi.shape[1] < size:
        pad = np.zeros((size, size), dtype=np.uint8)
        yo  = (size - roi.shape[0]) // 2
        xo  = (size - roi.shape[1]) // 2
        pad[yo:yo+roi.shape[0], xo:xo+roi.shape[1]] = roi
        roi = pad

    return roi


# =====================================================================
# STEP 2 — ENHANCEMENT (Gabor + CLAHE) — sama persis dengan main.py
# =====================================================================

def enhance_gabor(img_gray):
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
    clahe     = cv2.createCLAHE(clipLimit=CLAHE_CLIP, tileGridSize=CLAHE_TILE)
    return clahe.apply(gabor_max)


# =====================================================================
# STEP 3 — HOG-SGF EXTRACTION — sama persis dengan main.py
# =====================================================================

def extract_hog_sgf(img_gray):
    img_64 = cv2.resize(img_gray, (IMAGE_SIZE, IMAGE_SIZE))

    # HOG
    hog_feat = hog(
        img_64,
        orientations    = HOG_ORIENT,
        pixels_per_cell = (HOG_PIXELS, HOG_PIXELS),
        cells_per_block = (HOG_CELLS, HOG_CELLS),
        block_norm      = 'L2',
        visualize       = False
    )

    # SGF
    img_f = img_64.astype(np.float32)
    Ix    = cv2.Sobel(img_f, cv2.CV_32F, 1, 0, ksize=3)
    Iy    = cv2.Sobel(img_f, cv2.CV_32F, 0, 1, ksize=3)
    sgf_feats = []
    for theta in SGF_ANGLES:
        FR = np.cos(theta) * Ix + np.sin(theta) * Iy
        sgf_feats.append(np.mean(FR))
        sgf_feats.append(np.std(FR))

    # Gabung & L2 normalize
    combined = np.concatenate([hog_feat, np.array(sgf_feats, dtype=np.float32)])
    norm     = np.linalg.norm(combined)
    if norm > 0:
        combined = combined / norm

    return combined


# =====================================================================
# STEP 4 — LOAD MODEL & TRANSFORM
# =====================================================================

def load_models():
    try:
        scaler    = joblib.load(os.path.join(MODELS_DIR, "scaler.pkl"))
        pca       = joblib.load(os.path.join(MODELS_DIR, "pca.pkl"))
        threshold = joblib.load(os.path.join(MODELS_DIR, "threshold.pkl"))
        return scaler, pca, threshold
    except Exception as e:
        output_error(f"Gagal load model: {str(e)}")
        sys.exit(1)


def process_image(image_path):
    """
    Pipeline lengkap:
    foto → ROI (YOLO+centroid) → Gabor+CLAHE → HOG-SGF → Scaler → PCA → vektor
    """
    # Load gambar
    img = cv2.imread(image_path)
    if img is None:
        output_error(f"Gagal membaca gambar: {image_path}")
        sys.exit(1)

    # Pipeline ekstraksi
    roi      = extract_roi(img)       # YOLO + centroid ROI
    enhanced = enhance_gabor(roi)     # Gabor + CLAHE
    feat     = extract_hog_sgf(enhanced)  # HOG-SGF (1812 dim)

    # Load model lalu transform
    scaler, pca, threshold = load_models()
    feat_scaled = scaler.transform([feat])   # StandardScaler
    feat_pca    = pca.transform(feat_scaled) # PCA → 200 dim

    return feat_pca[0].tolist(), threshold


# =====================================================================
# MAIN
# =====================================================================

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", required=True, help="Path ke file gambar")
    args = parser.parse_args()

    if not os.path.exists(args.image):
        output_error(f"File tidak ditemukan: {args.image}")
        sys.exit(1)

    vector, threshold = process_image(args.image)
    output_success(vector, threshold)