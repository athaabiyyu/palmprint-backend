"""
enhancement_v3_shadow_robust.py
================================
Pipeline enhancement baru untuk palmprint ROI yang lebih tahan terhadap
bayangan tajam dan pencahayaan tidak merata (kasus kamera depan HP,
cahaya dari samping / matahari).

Beda pendekatan dengan pipeline lama (DoG->Gabor->CLAHE->Sharpen):

1. Koreksi iluminasi dilakukan di RUANG LOG (homomorphic-style), bukan
   subtraksi linear biasa -> lebih sesuai dengan sifat fisik bayangan
   yang MULTIPLIKATIF (meredam cahaya secara proporsional), bukan aditif.

2. Estimasi background/iluminasi pakai BILATERAL FILTER, bukan Gaussian
   blur biasa -> bilateral filter itu edge-preserving-smoothing terbalik:
   dia melicinkan area homogen tapi TIDAK menembus tepi kontras tinggi.
   Ini penting karena Gaussian blur biasa akan "membocorkan" sebagian pola
   garis tangan ke dalam estimasi background di sekitar tepi bayangan,
   sehingga koreksinya jadi tidak sempurna persis di garis batas bayangan
   (area yang justru paling sering salah baca sebagai fitur).

3. Ridge/garis diekstrak pakai Frangi (Hessian-based vesselness), bukan
   Gabor sebagai tahap utama -> Frangi merespons KELENGKUNGAN lokal
   (eigenvalue rasio Hessian) bukan gradient intensitas mentah, sehingga
   jauh kurang sensitif terhadap gradient pencahayaan yang landai
   (misal cahaya matahari difus dari samping tanpa bayangan tajam).

4. Ada tahap tambahan khusus: SUPPRESS GARIS LURUS PANJANG. Ini
   menyasar akar masalah spesifik "batas bayangan kebaca sebagai
   fitur" -> garis telapak tangan itu melengkung & relatif pendek dalam
   ROI, sedangkan batas bayangan (dari jari/tangan yang menghalangi
   cahaya) cenderung panjang & lurus. Dideteksi pakai Hough line lalu
   diredam sebelum tahap ridge enhancement final.

Cara pakai: ganti extract_feature_old / extract_feature_new di notebook
kalian dengan extract_feature_v3, lalu jalankan ulang cell perbandingan
genuine-vs-impostor separation yang sudah ada (cell dengan
compute_similarities) untuk membandingkan angka separasinya secara
objektif -- terutama pada sample-sample yang kalian tahu punya bayangan
tajam (idx 13, 19, 27 di notebook debug kalian).
"""

import cv2
import numpy as np
from skimage.filters import frangi


# ============================================================
# 1. Koreksi iluminasi homomorfik (edge-preserving background)
# ============================================================
def homomorphic_illumination_correction(
    img_gray,
    sigma_space=25,
    sigma_color=25,
    gamma_low=0.55,   # bobot komponen iluminasi (rendahkan supaya bayangan makin rata)
    gamma_high=1.6,   # bobot komponen reflektansi/detail garis (naikkan supaya garis makin jelas)
):
    img_f = img_gray.astype(np.float32) + 1.0
    log_img = np.log(img_f)

    # Estimasi iluminasi di ruang log, pakai bilateral filter supaya
    # tepi bayangan tajam tidak "meleleh" masuk ke background.
    illumination_log = cv2.bilateralFilter(
        log_img, d=0, sigmaColor=sigma_color / 255.0 * np.log(255.0), sigmaSpace=sigma_space
    )
    reflectance_log = log_img - illumination_log

    out_log = gamma_low * illumination_log + gamma_high * reflectance_log
    out = np.exp(out_log) - 1.0
    return cv2.normalize(out, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


# ============================================================
# 2. Deteksi & redam garis lurus panjang (kemungkinan batas bayangan)
# ============================================================
def suppress_long_straight_lines(
    img_gray,
    min_line_length=45,   # garis lebih panjang dari ini dicurigai batas bayangan, bukan garis tangan
    max_line_gap=6,
    dilate_ksize=5,
    blend_alpha=0.35,     # seberapa kuat garis lurus panjang diredam (0=tidak diredam, 1=dihapus total)
):
    edges = cv2.Canny(img_gray, 40, 120)
    lines = cv2.HoughLinesP(
        edges, 1, np.pi / 180, threshold=30,
        minLineLength=min_line_length, maxLineGap=max_line_gap,
    )

    if lines is None:
        return img_gray

    mask = np.zeros_like(img_gray, dtype=np.uint8)
    for x1, y1, x2, y2 in lines[:, 0]:
        cv2.line(mask, (x1, y1), (x2, y2), 255, thickness=2)

    mask = cv2.dilate(mask, np.ones((dilate_ksize, dilate_ksize), np.uint8))
    mask_f = (mask.astype(np.float32) / 255.0) * blend_alpha

    # Redam (bukan hapus total) area yang kena garis panjang-lurus,
    # dengan menariknya ke nilai rata-rata lokal, supaya tidak
    # menghapus garis tangan asli yang kebetulan lurus & agak panjang.
    local_mean = cv2.blur(img_gray, (9, 9)).astype(np.float32)
    img_f = img_gray.astype(np.float32)
    result = img_f * (1 - mask_f) + local_mean * mask_f
    return np.clip(result, 0, 255).astype(np.uint8)


# ============================================================
# 3. Ridge enhancement dengan Frangi (multi-skala)
# ============================================================
def isolate_ridges_frangi(img_gray, scale_range=(2, 5), scale_step=1):
    img_f = img_gray.astype(np.float64) / 255.0
    ridges = frangi(
        img_f,
        sigmas=range(scale_range[0], scale_range[1] + 1, scale_step),
        black_ridges=True,
    )
    return cv2.normalize(ridges, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


# ============================================================
# 4. Sharpen ringan (sama seperti pipeline lama kalian)
# ============================================================
def sharpen_light(img_gray):
    blur = cv2.GaussianBlur(img_gray, (5, 5), 2)
    return cv2.addWeighted(img_gray, 1.3, blur, -0.3, 0)


# ============================================================
# 5. Deteksi bayangan tajam (opsional, untuk quality-gate saat register/identify)
# ============================================================
def detect_hard_shadow(img_gray, edge_thresh=55, min_area_ratio=0.03):
    """
    Heuristik cepat: apakah ROI kemungkinan punya bayangan tajam (bukan
    sekadar gradient pencahayaan halus)? Bisa dipakai untuk minta user
    foto ulang / pindah posisi kalau ratio-nya tinggi, sebelum masuk ke
    pipeline ekstraksi fitur.
    """
    grad = cv2.Laplacian(img_gray, cv2.CV_32F, ksize=5)
    strong_edges = (np.abs(grad) > edge_thresh).astype(np.uint8)
    closed = cv2.morphologyEx(strong_edges, cv2.MORPH_CLOSE, np.ones((15, 15), np.uint8))
    ratio = closed.sum() / 255.0 / img_gray.size
    return bool(ratio > min_area_ratio), float(ratio)


# ============================================================
# Pipeline lengkap
# ============================================================
def enhance_v3(
    img_gray,
    use_line_suppression=True,
    min_line_length=45,
    blend_alpha=0.35,
    sigma_space=25,
    gamma_low=0.55,
    gamma_high=1.6,
    frangi_scale_range=(2, 5),
):
    """Pipeline enhancement baru, urutan: homomorphic -> (opsional) suppress
    garis panjang -> Frangi ridge -> sharpen ringan.

    Semua parameter kunci di-expose di sini supaya bisa diablasi langsung
    dari notebook tanpa perlu edit file ini lagi:
      - sigma_space, gamma_low, gamma_high  -> kontrol homomorphic correction
      - frangi_scale_range                  -> kontrol skala ridge Frangi
      - use_line_suppression / min_line_length / blend_alpha -> tahap suppress garis
    """
    corrected = homomorphic_illumination_correction(
        img_gray, sigma_space=sigma_space, gamma_low=gamma_low, gamma_high=gamma_high
    )
    if use_line_suppression:
        corrected = suppress_long_straight_lines(
            corrected, min_line_length=min_line_length, blend_alpha=blend_alpha
        )
    ridges = isolate_ridges_frangi(corrected, scale_range=frangi_scale_range)
    return sharpen_light(ridges)


def extract_feature_v3(roi_gray, extract_hog_sgf_fn, **enhance_kwargs):
    """
    Panggil ini di notebook kalian sebagai pengganti extract_feature_old
    / extract_feature_new. `extract_hog_sgf_fn` adalah fungsi
    extract_hog_sgf yang sudah ada di notebook kalian (di-pass sebagai
    parameter supaya file ini tidak perlu import Config kalian).
    `**enhance_kwargs` diteruskan ke enhance_v3 (mis. use_line_suppression=False).

    Contoh pemakaian di notebook:

        from enhancement_v3_shadow_robust import extract_feature_v3, enhance_v3

        def extract_feature_v3_wrapped(roi_gray):
            return extract_feature_v3(roi_gray, extract_hog_sgf)

        features_v3[f] = extract_feature_v3_wrapped(roi)
    """
    enhanced = enhance_v3(roi_gray, **enhance_kwargs)
    return extract_hog_sgf_fn(enhanced, visualize=False)