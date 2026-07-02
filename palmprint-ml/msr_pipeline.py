import cv2
import numpy as np


def single_scale_retinex(img_gray, sigma):
    """Retinex 1 skala: log(I) - log(I * Gaussian_blur)."""
    img_f = img_gray.astype(np.float32) + 1.0  # hindari log(0)
    blur = cv2.GaussianBlur(img_f, (0, 0), sigmaX=sigma)
    retinex = np.log10(img_f) - np.log10(blur + 1.0)
    return retinex


def multi_scale_retinex(img_gray, sigmas=(3, 12, 40)):
    """
    MSR: kombinasi beberapa skala Retinex.
    Sigma kecil -> tangkap detail halus (garis tipis)
    Sigma besar -> tangkap variasi iluminasi skala besar (shadow)

    PENTING: sigma di sini di-skala untuk ROI KECIL (128x128px).
    Default literatur (15/80/250) itu untuk foto ukuran normal (500-1000px+)
    dan akan over-smooth kalau dipakai langsung di ROI sekecil ini.
    """
    retinex_sum = np.zeros_like(img_gray, dtype=np.float32)
    for sigma in sigmas:
        retinex_sum += single_scale_retinex(img_gray, sigma)
    retinex_avg = retinex_sum / len(sigmas)

    lo, hi = np.percentile(retinex_avg, [2, 98])
    retinex_clipped = np.clip(retinex_avg, lo, hi)
    return cv2.normalize(retinex_clipped, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)


def msr_gabor_pipeline(img_gray, gabor_thetas, gabor_ksize=21, gabor_gamma=0.5):
    """Pipeline baru: MSR (illumination-invariant) -> Gabor bank -> normalize."""
    msr = multi_scale_retinex(img_gray)

    responses = []
    scales = [{"sigma": 3.5, "lambda": 12}, {"sigma": 2.0, "lambda": 7}]
    for scale in scales:
        for theta in gabor_thetas:
            kernel = cv2.getGaborKernel(
                (gabor_ksize, gabor_ksize),
                scale["sigma"], theta, scale["lambda"], gabor_gamma, 0, cv2.CV_32F,
            )
            resp = cv2.filter2D(msr.astype(np.float32), cv2.CV_32F, kernel)
            responses.append(np.abs(resp))

    gabor_max = np.max(responses, axis=0)
    gabor_mean = np.mean(responses, axis=0)
    combined = 0.4 * gabor_max + 0.6 * gabor_mean
    return msr, cv2.normalize(combined, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)