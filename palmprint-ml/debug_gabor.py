"""
debug_gabor_individual.py
==========================
Tujuan: melihat respons SETIAP kernel Gabor (per scale x per theta) secara
terpisah, SEBELUM dikombinasikan (max/mean/energy). Ini membantu menemukan
di parameter mana pola grid/artefak basket-weave mulai muncul.

Cara pakai:
1. Letakkan file ini sejajar dengan script utama (yang punya roi_mediapipe.py)
2. Sesuaikan IMG_PATH ke salah satu file di Config.DATASET_PATH
3. Jalankan: python debug_gabor_individual.py
"""

import os
import cv2
import numpy as np
import matplotlib.pyplot as plt

from roi_mediapipe import detect_palm_opencv

# =====================================================
# KONFIGURASI — samakan dengan script utama
# =====================================================
IMG_PATH = r"D:\xampp\htdocs\palmprint-backend\palmprint-ml\dataset\subject_001_left_hand_01.tiff"

GABOR_KSIZE = 21
GABOR_GAMMA = 0.5
THETAS = np.deg2rad([0, 22.5, 45, 67.5, 90, 112.5, 135, 157.5])

# Dua kandidat skala untuk dibandingkan langsung:
SCALE_SETS = {
    "ORIGINAL (sigma/lambda rasio kecil -> diduga penyebab grid)": [
        {"sigma": 3.5, "lambda": 12.0},  # rasio 0.29
        {"sigma": 1.2, "lambda": 4.0},   # rasio 0.30
    ],
    "USULAN FIX (rasio ~0.40, lebih stabil)": [
        {"sigma": 4.0, "lambda": 10.0},  # rasio 0.40
        {"sigma": 2.0, "lambda": 5.0},   # rasio 0.40
    ],
}


def normalize_illumination(img_gray):
    img_f = img_gray.astype(np.float32)
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=1.0)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=5.0)
    dog = g_small - g_large
    dog = dog - dog.mean()
    return cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


def gabor_response(img_gray, sigma, lam, theta):
    kernel = cv2.getGaborKernel(
        ksize=(GABOR_KSIZE, GABOR_KSIZE),
        sigma=sigma,
        theta=theta,
        lambd=lam,
        gamma=GABOR_GAMMA,
        psi=0,
        ktype=cv2.CV_32F,
    )
    resp = cv2.filter2D(img_gray.astype(np.float32), cv2.CV_32F, kernel)
    return np.abs(resp)


def main():
    img_s = cv2.imread(IMG_PATH)
    if img_s is None:
        raise FileNotFoundError(f"Tidak bisa membaca: {IMG_PATH}")

    roi_gray, dbg = detect_palm_opencv(img_s, debug=True)
    dog_s = normalize_illumination(roi_gray)

    for set_name, scales in SCALE_SETS.items():
        n_scales = len(scales)
        n_thetas = len(THETAS)

        fig, axes = plt.subplots(
            n_scales, n_thetas, figsize=(2.2 * n_thetas, 2.4 * n_scales)
        )
        fig.suptitle(set_name, fontsize=12, fontweight="bold")

        for i, scale in enumerate(scales):
            for j, theta in enumerate(THETAS):
                resp = gabor_response(dog_s, scale["sigma"], scale["lambda"], theta)
                resp_norm = cv2.normalize(resp, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)

                ax = axes[i, j] if n_scales > 1 else axes[j]
                ax.imshow(resp_norm, cmap="gray")
                ax.set_title(
                    f"s={scale['sigma']} l={scale['lambda']}\ntheta={int(np.rad2deg(theta))}",
                    fontsize=7,
                )
                ax.axis("off")

        plt.tight_layout()
        fname = f"gabor_individual_{set_name[:10].strip().replace(' ', '_')}.png"
        plt.savefig(fname, dpi=110, bbox_inches="tight")
        print(f"Tersimpan: {fname}")
        plt.show()

    # Bonus: tampilkan energy-based combination (sqrt sum of squares per scale)
    # sebagai alternatif kombinasi yang lebih stabil dibanding max() mentah
    fig2, axes2 = plt.subplots(1, len(SCALE_SETS) + 1, figsize=(5 * (len(SCALE_SETS) + 1), 5))
    axes2[0].imshow(roi_gray, cmap="gray")
    axes2[0].set_title("ROI asli", fontsize=9)
    axes2[0].axis("off")

    for idx, (set_name, scales) in enumerate(SCALE_SETS.items(), start=1):
        per_scale_energy = []
        for scale in scales:
            responses = [
                gabor_response(dog_s, scale["sigma"], scale["lambda"], t) for t in THETAS
            ]
            energy = np.sqrt(np.sum(np.square(responses), axis=0))
            per_scale_energy.append(energy)
        combined = np.mean(per_scale_energy, axis=0)
        combined_norm = cv2.normalize(combined, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)
        clahe = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
        final = clahe.apply(combined_norm)

        axes2[idx].imshow(final, cmap="gray")
        axes2[idx].set_title(f"Energy-combine\n{set_name[:25]}", fontsize=8)
        axes2[idx].axis("off")

    plt.tight_layout()
    plt.savefig("gabor_energy_combination_compare.png", dpi=110, bbox_inches="tight")
    print("Tersimpan: gabor_energy_combination_compare.png")
    plt.show()


if __name__ == "__main__":
    main()