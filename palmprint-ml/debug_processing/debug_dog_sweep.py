"""
debug_dog_sweep.py
====================
Tujuan: mencari sigma_large terbaik untuk normalize_illumination (DoG),
dan membandingkan normalisasi min-max polos vs percentile clipping.

Cara pakai:
1. Letakkan file ini sejajar dengan roi_mediapipe.py
2. Sesuaikan IMG_PATH
3. Jalankan: python debug_dog_sweep.py
"""

import cv2
import numpy as np
import sys
import os
import matplotlib.pyplot as plt

sys.path.append(
    os.path.abspath(
        os.path.join(os.path.dirname(__file__), "..")
    )
)

from roi_mediapipe import detect_palm_opencv

# =====================================================
IMG_PATH = r"D:\xampp\htdocs\palmprint-backend\palmprint-ml\dataset\subject_001_left_hand_01.tiff"

SIGMA_SMALL = 1.0
SIGMA_LARGE_CANDIDATES = [5.0, 10.0, 15.0, 20.0, 25.0, 30.0]
PERCENTILE_CLIP = (1, 99)  # lo, hi


def dog_raw(img_f, sigma_small, sigma_large):
    g_small = cv2.GaussianBlur(img_f, (0, 0), sigmaX=sigma_small)
    g_large = cv2.GaussianBlur(img_f, (0, 0), sigmaX=sigma_large)
    dog = g_small - g_large
    dog = dog - dog.mean()
    return dog


def to_uint8_minmax(dog):
    return cv2.normalize(dog, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


def to_uint8_percentile(dog, lo_p, hi_p):
    lo, hi = np.percentile(dog, [lo_p, hi_p])
    clipped = np.clip(dog, lo, hi)
    return cv2.normalize(clipped, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


def main():
    img_s = cv2.imread(IMG_PATH)
    if img_s is None:
        raise FileNotFoundError(f"Tidak bisa membaca: {IMG_PATH}")

    roi_gray, dbg = detect_palm_opencv(img_s, debug=True)
    img_f = roi_gray.astype(np.float32)

    n = len(SIGMA_LARGE_CANDIDATES)

    # =====================================================
    # BARIS 1: min-max normalization (cara lama)
    # BARIS 2: percentile clipping (usulan)
    # =====================================================
    fig, axes = plt.subplots(3, n, figsize=(2.6 * n, 8.2))
    fig.suptitle(
        f"DoG Sweep sigma_large (sigma_small={SIGMA_SMALL} tetap)\n"
        f"Baris 1: MIN-MAX  |  Baris 2: PERCENTILE CLIP {PERCENTILE_CLIP}",
        fontsize=11, fontweight="bold"
    )

    # ROI asli sebagai acuan (ditaruh di kolom pertama, baris ke-3)
    for j, sigma_large in enumerate(SIGMA_LARGE_CANDIDATES):
        dog = dog_raw(img_f, SIGMA_SMALL, sigma_large)

        out_minmax = to_uint8_minmax(dog)
        out_pct = to_uint8_percentile(dog, *PERCENTILE_CLIP)

        axes[0, j].imshow(out_minmax, cmap="gray")
        axes[0, j].set_title(f"sigma_large={sigma_large}\n(min-max)", fontsize=8)
        axes[0, j].axis("off")

        axes[1, j].imshow(out_pct, cmap="gray")
        axes[1, j].set_title(f"sigma_large={sigma_large}\n(percentile)", fontsize=8)
        axes[1, j].axis("off")

        # baris 3: selisih absolut terhadap ROI asli (resize biar sama ukuran)
        roi_resized = cv2.resize(roi_gray, (out_pct.shape[1], out_pct.shape[0]))
        diff = cv2.absdiff(roi_resized, out_pct)
        axes[2, j].imshow(diff, cmap="inferno")
        axes[2, j].set_title(f"|diff| vs ROI asli", fontsize=8)
        axes[2, j].axis("off")

    plt.tight_layout()
    plt.savefig("dog_sweep_comparison.png", dpi=120, bbox_inches="tight")
    print("Tersimpan: dog_sweep_comparison.png")
    plt.show()

    # =====================================================
    # Bonus: line profile (potongan horizontal) untuk lihat
    # ketajaman & kontinuitas garis secara kuantitatif
    # =====================================================
    row_idx = roi_gray.shape[0] // 2  # baris tengah ROI

    plt.figure(figsize=(10, 5))
    plt.plot(roi_gray[row_idx, :], label="ROI asli", linewidth=1.5, alpha=0.6)

    for sigma_large in SIGMA_LARGE_CANDIDATES:
        dog = dog_raw(img_f, SIGMA_SMALL, sigma_large)
        out_pct = to_uint8_percentile(dog, *PERCENTILE_CLIP)
        out_pct_resized = cv2.resize(out_pct, (roi_gray.shape[1], roi_gray.shape[0]))
        plt.plot(out_pct_resized[row_idx, :], label=f"sigma_large={sigma_large}", linewidth=1)

    plt.title(f"Line profile baris tengah (row={row_idx}) — bandingkan ketajaman tepi garis")
    plt.xlabel("kolom piksel")
    plt.ylabel("intensitas")
    plt.legend(fontsize=8)
    plt.tight_layout()
    plt.savefig("dog_sweep_line_profile.png", dpi=120, bbox_inches="tight")
    print("Tersimpan: dog_sweep_line_profile.png")
    plt.show()


if __name__ == "__main__":
    main()