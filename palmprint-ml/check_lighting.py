import cv2
import numpy as np

def check_lighting_quality(roi_gray, asym_threshold=30, verbose=False):
    """
    Deteksi pencahayaan tidak merata / shadow directional pada ROI palmprint.

    Prinsip: bandingkan mean brightness antar-belahan (kiri/kanan, atas/bawah).
    Shadow dari cahaya samping akan bikin satu sisi jauh lebih gelap dari sisi
    lainnya secara KONTINU (bukan tersebar acak seperti kotoran).

    Return: dict berisi status + pesan, biar gampang di-log/di-tune.
    """
    h, w = roi_gray.shape
    gray_f = roi_gray.astype(np.float32)

    # ── Split kiri/kanan, atas/bawah ──
    left  = gray_f[:, : w // 2]
    right = gray_f[:, w // 2 :]
    top    = gray_f[: h // 2, :]
    bottom = gray_f[h // 2 :, :]

    mean_left, mean_right   = left.mean(), right.mean()
    mean_top,  mean_bottom  = top.mean(), bottom.mean()

    diff_h = mean_left - mean_right   # positif = kanan lebih gelap
    diff_v = mean_top - mean_bottom   # positif = bawah lebih gelap

    result = {
        "mean_left": mean_left, "mean_right": mean_right,
        "mean_top": mean_top, "mean_bottom": mean_bottom,
        "diff_h": diff_h, "diff_v": diff_v,
        "ok": True, "message": None,
    }

    # ── Cek asimetri horizontal ──
    if abs(diff_h) > asym_threshold:
        sisi = "kanan" if diff_h > 0 else "kiri"
        result["ok"] = False
        result["message"] = f"Bayangan terdeteksi di sisi {sisi} (diff={diff_h:.1f})"

    # ── Cek asimetri vertikal ──
    if abs(diff_v) > asym_threshold:
        sisi = "bawah" if diff_v > 0 else "atas"
        result["ok"] = False
        msg2 = f"Bayangan terdeteksi di sisi {sisi} (diff={diff_v:.1f})"
        result["message"] = (result["message"] + " & " + msg2) if result["message"] else msg2

    # ── Cek lokal: grid kecil untuk tangkap shadow patch yang kecil/tidak simetris ──
    grid_n = 4  # 4x4 = 16 blok
    block_h, block_w = h // grid_n, w // grid_n
    block_means = np.zeros((grid_n, grid_n), dtype=np.float32)
    for gy in range(grid_n):
        for gx in range(grid_n):
            block = gray_f[gy*block_h:(gy+1)*block_h, gx*block_w:(gx+1)*block_w]
            block_means[gy, gx] = block.mean()

    block_std = block_means.std()
    block_min = block_means.min()
    block_max = block_means.max()
    local_range = block_max - block_min  # selisih blok tergelap vs terterang

    result["block_std"] = block_std
    result["local_range"] = local_range
    result["block_means"] = block_means

    # Threshold ini JUGA perlu dikalibrasi pakai data kamu (starting point saja)
    LOCAL_STD_THRESHOLD = 18
    LOCAL_RANGE_THRESHOLD = 55

    if block_std > LOCAL_STD_THRESHOLD or local_range > LOCAL_RANGE_THRESHOLD:
        result["ok"] = False
        msg3 = f"Pencahayaan tidak merata secara lokal (std={block_std:.1f}, range={local_range:.1f})"
        result["message"] = (result["message"] + " & " + msg3) if result["message"] else msg3

    if verbose:
        print(f"[Lighting] L={mean_left:.1f} R={mean_right:.1f} "
              f"T={mean_top:.1f} B={mean_bottom:.1f} "
              f"diff_h={diff_h:.1f} diff_v={diff_v:.1f} "
              f"block_std={block_std:.1f} local_range={local_range:.1f} -> ok={result['ok']}")

    return result


def visualize_lighting_check(roi_gray, result):
    """Bantu visual debug: overlay garis pembagi + angka mean tiap region."""
    import matplotlib.pyplot as plt
    h, w = roi_gray.shape
    fig, ax = plt.subplots(figsize=(5, 5))
    ax.imshow(roi_gray, cmap="gray")
    ax.axvline(w // 2, color="red", linestyle="--")
    ax.axhline(h // 2, color="red", linestyle="--")
    ax.set_title(f"ok={result['ok']} | {result['message'] or 'lighting fine'}")
    ax.text(5, 15, f"L={result['mean_left']:.0f}", color="yellow", fontsize=9)
    ax.text(w - 40, 15, f"R={result['mean_right']:.0f}", color="yellow", fontsize=9)
    ax.text(5, h - 5, f"B={result['mean_bottom']:.0f}", color="cyan", fontsize=9)
    ax.text(5, 25, f"T={result['mean_top']:.0f}", color="cyan", fontsize=9)
    ax.axis("off")
    plt.tight_layout()
    plt.show()


def visualize_lighting_batch(images_and_labels):
    """
    Tampilkan beberapa ROI + hasil check_lighting_quality dalam SATU baris.

    images_and_labels: list of tuples (roi_gray, label_str)
        label_str biasanya nama filenya, buat identifikasi.
    """
    import matplotlib.pyplot as plt

    n = len(images_and_labels)
    fig, axes = plt.subplots(1, n, figsize=(5 * n, 5))
    if n == 1:
        axes = [axes]  # biar tetap bisa di-loop kalau cuma 1 gambar

    for ax, (roi_gray, label) in zip(axes, images_and_labels):
        result = check_lighting_quality(roi_gray)
        h, w = roi_gray.shape

        ax.imshow(roi_gray, cmap="gray")
        ax.axvline(w // 2, color="red", linestyle="--")
        ax.axhline(h // 2, color="red", linestyle="--")
        ax.set_title(f"{label}\nok={result['ok']}\nrange={result['local_range']:.1f}",
                     fontsize=9)
        ax.text(5, 15, f"L={result['mean_left']:.0f}", color="yellow", fontsize=8)
        ax.text(w - 40, 15, f"R={result['mean_right']:.0f}", color="yellow", fontsize=8)
        ax.text(5, h - 5, f"B={result['mean_bottom']:.0f}", color="cyan", fontsize=8)
        ax.text(5, 25, f"T={result['mean_top']:.0f}", color="cyan", fontsize=8)
        ax.axis("off")

    plt.tight_layout()
    plt.show()