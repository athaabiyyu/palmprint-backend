"""
debug_roi_viewer.py
====================
Script debug untuk melihat apa yang Python terima dan hasilkan.

Jalankan:
  python debug_roi_viewer.py path/to/foto.jpg
  python debug_roi_viewer.py path/to/folder/   (batch semua jpg/png di folder)

Output:
  debug_output/
    ├── 01_original.jpg          → gambar asli dari Flutter
    ├── 02_aligned.jpg           → setelah rotasi alignment (Fase 1A)
    ├── 03_landmarks.jpg         → landmark MediaPipe di-plot di gambar
    ├── 04_roi_crop.jpg          → ROI 200x200 hasil crop (grayscale)
    ├── 05_enhanced.jpg          → setelah Gabor + CLAHE
    └── 06_summary.jpg           → semua panel dalam 1 gambar
"""

import cv2
import numpy as np
import os
import sys

# Pastikan roi_mediapipe.py ada di folder yang sama
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from roi_mediapipe import detect_palm_opencv, PALM_LANDMARK_IDS, ROI_SIZE

# Gabor + CLAHE (copy dari palmprint_api.py)
GABOR_THETAS  = [0, np.pi/4, np.pi/2, 3*np.pi/4]
GABOR_KSIZE   = 21
GABOR_SIGMA   = 4.0
GABOR_LAMBDA  = 10.0
GABOR_GAMMA   = 0.5
CLAHE_CLIP    = 2.0
CLAHE_TILE    = (8, 8)

OUTPUT_DIR = 'debug_output'
os.makedirs(OUTPUT_DIR, exist_ok=True)


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


def draw_landmarks_on_image(img_bgr, landmarks, cx, cy, roi_rect, angle):
    """
    Plot landmark, centroid, dan ROI box di atas gambar.
    """
    vis = img_bgr.copy()
    h, w = vis.shape[:2]

    # Gambar landmark points
    if landmarks:
        colors = [
            (0, 165, 255),   # wrist       - orange
            (255, 0, 0),     # thumb_cmc   - biru
            (255, 0, 0),     # index_mcp   - biru
            (255, 0, 0),     # middle_mcp  - biru
            (255, 0, 0),     # ring_mcp    - biru
            (255, 0, 0),     # pinky_mcp   - biru
        ]
        labels = ['wrist', 'thumb', 'index', 'middle', 'ring', 'pinky']
        for i, (px, py) in enumerate(landmarks):
            color = colors[i] if i < len(colors) else (255, 255, 0)
            cv2.circle(vis, (px, py), 8, color, -1)
            cv2.circle(vis, (px, py), 8, (255, 255, 255), 2)
            cv2.putText(vis, labels[i], (px + 10, py),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.45, (255, 255, 255), 1)

        # Garis wrist ke middle MCP (sumbu alignment)
        wrist  = landmarks[0]
        middle = landmarks[3]  # middle MCP (index ke-3 di PALM_LANDMARK_IDS)
        cv2.line(vis, wrist, middle, (0, 255, 255), 2)

    # Centroid
    cv2.circle(vis, (cx, cy), 12, (0, 255, 0), -1)
    cv2.circle(vis, (cx, cy), 12, (255, 255, 255), 2)
    cv2.putText(vis, 'centroid', (cx + 14, cy),
               cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 1)

    # ROI box
    x1, y1, x2, y2 = roi_rect
    cv2.rectangle(vis, (x1, y1), (x2, y2), (0, 255, 0), 3)
    cv2.putText(vis, f'ROI {ROI_SIZE}x{ROI_SIZE}px', (x1, y1 - 10),
               cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)

    # Info angle
    cv2.putText(vis, f'Alignment angle: {angle:.1f} deg',
               (20, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 255), 2)

    return vis


def make_summary(img_bgr, img_aligned, img_landmarks, roi_gray, enhanced, fname, dbg):
    """
    Buat 1 gambar summary dengan semua panel side by side.
    """
    target_h = 300

    def resize_to_h(img, h):
        scale = h / img.shape[0]
        w = int(img.shape[1] * scale)
        return cv2.resize(img, (w, h))

    def to_bgr(img):
        if len(img.shape) == 2:
            return cv2.cvtColor(img, cv2.COLOR_GRAY2BGR)
        return img

    panels = [
        ('1. Original',  resize_to_h(img_bgr,        target_h)),
        ('2. Aligned',   resize_to_h(img_aligned,     target_h)),
        ('3. Landmarks', resize_to_h(img_landmarks,   target_h)),
        ('4. ROI crop',  resize_to_h(to_bgr(roi_gray),target_h)),
        ('5. Enhanced',  resize_to_h(to_bgr(enhanced), target_h)),
    ]

    # Tambahkan label di bawah tiap panel
    labeled = []
    for title, panel in panels:
        h, w = panel.shape[:2]
        label_bar = np.zeros((36, w, 3), dtype=np.uint8)
        cv2.putText(label_bar, title, (8, 24),
                   cv2.FONT_HERSHEY_SIMPLEX, 0.55, (200, 200, 200), 1)
        labeled.append(np.vstack([panel, label_bar]))

    summary = np.hstack(labeled)

    # Header info
    header = np.zeros((60, summary.shape[1], 3), dtype=np.uint8)
    info = (f'{fname}  |  angle={dbg["angle"]:.1f}deg  |  '
            f'centroid=({dbg["cx"]},{dbg["cy"]})  |  '
            f'fallback={dbg["fallback"]}  |  area={dbg["area"]:.0f}px2')
    cv2.putText(header, info, (12, 38),
               cv2.FONT_HERSHEY_SIMPLEX, 0.52, (180, 220, 255), 1)

    return np.vstack([header, summary])


def process_image(img_path):
    fname = os.path.basename(img_path)
    stem  = os.path.splitext(fname)[0]
    print(f'\n[DEBUG] Processing: {fname}')

    img = cv2.imread(img_path)
    if img is None:
        print(f'  ERROR: Gagal baca gambar {img_path}')
        return

    print(f'  Ukuran gambar  : {img.shape[1]}x{img.shape[0]}px')

    # Jalankan full pipeline
    roi, dbg = detect_palm_opencv(img, debug=True)
    enhanced = enhance_gabor(roi)

    img_aligned   = dbg['img_aligned']
    img_landmarks = draw_landmarks_on_image(
        img_aligned,
        dbg['landmarks'],
        dbg['cx'], dbg['cy'],
        dbg['roi_rect'],
        dbg['angle']
    )

    # Simpan individual panels
    cv2.imwrite(f'{OUTPUT_DIR}/{stem}_01_original.jpg',  img)
    cv2.imwrite(f'{OUTPUT_DIR}/{stem}_02_aligned.jpg',   img_aligned)
    cv2.imwrite(f'{OUTPUT_DIR}/{stem}_03_landmarks.jpg', img_landmarks)
    cv2.imwrite(f'{OUTPUT_DIR}/{stem}_04_roi.jpg',       roi)
    cv2.imwrite(f'{OUTPUT_DIR}/{stem}_05_enhanced.jpg',  enhanced)

    # Simpan summary
    summary = make_summary(img, img_aligned, img_landmarks, roi, enhanced, fname, dbg)
    cv2.imwrite(f'{OUTPUT_DIR}/{stem}_06_summary.jpg', summary)

    print(f'  Angle alignment  : {dbg["angle"]:.2f} derajat')
    print(f'  Centroid         : ({dbg["cx"]}, {dbg["cy"]})')
    print(f'  ROI rect         : {dbg["roi_rect"]}')
    print(f'  Dynamic ROI size : {dbg["dynamic_roi_size"]}px (sebelum resize ke {ROI_SIZE}px)')
    print(f'  Fallback         : {dbg["fallback"]}')
    print(f'  Palm area        : {dbg["area"]:.0f} px2')
    print(f'  Output summary   : {OUTPUT_DIR}/{stem}_06_summary.jpg')

    if dbg['fallback']:
        print('  ⚠️  PERINGATAN: MediaPipe gagal deteksi tangan!')
        print('     ROI diambil dari center gambar (tidak akurat).')
        print('     Pastikan tangan terlihat jelas dan pencahayaan cukup.')

    # Analisis kualitas ROI
    lap_var = cv2.Laplacian(roi, cv2.CV_64F).var()
    mean_br = roi.mean()
    std_br  = roi.std()
    print(f'\n  === Analisis Kualitas ROI ===')
    print(f'  Blur score (Laplacian var) : {lap_var:.1f}  {"OK" if lap_var > 50 else "BLUR - terlalu blur!"}')
    print(f'  Kecerahan rata-rata        : {mean_br:.1f}  {"OK" if 30 < mean_br < 220 else "MASALAH - terlalu gelap/terang!"}')
    print(f'  Kontras (std)              : {std_br:.1f}  {"OK" if std_br > 15 else "RENDAH - kurang kontras!"}')


def main():
    if len(sys.argv) < 2:
        print('Usage:')
        print('  python debug_roi_viewer.py foto.jpg')
        print('  python debug_roi_viewer.py folder/')
        sys.exit(0)

    target = sys.argv[1]

    if os.path.isdir(target):
        exts  = ('.jpg', '.jpeg', '.png', '.bmp', '.tiff', '.tif')
        files = [os.path.join(target, f) for f in os.listdir(target)
                 if f.lower().endswith(exts)
                 and '_annotated' not in f]   # skip file annotated dari Flutter
        if not files:
            print(f'Tidak ada gambar di folder: {target}')
            sys.exit(0)
        print(f'Ditemukan {len(files)} gambar di {target}')
        for f in sorted(files):
            process_image(f)
    elif os.path.isfile(target):
        process_image(target)
    else:
        print(f'File/folder tidak ditemukan: {target}')
        sys.exit(1)

    print(f'\n✓ Semua output tersimpan di folder: {OUTPUT_DIR}/')
    print(f'  Buka file *_06_summary.jpg untuk melihat semua panel sekaligus.')


if __name__ == '__main__':
    main()