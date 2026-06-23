import os
import cv2

from roi_mediapipe import detect_palm_opencv

# =====================================================
# FOLDER
# =====================================================

INPUT_FOLDER = r"D:\xampp\htdocs\palmprint-backend\palmprint-ml\testing"

ROI_FOLDER = "output_roi"
DEBUG_FOLDER = "output_debug"

os.makedirs(ROI_FOLDER, exist_ok=True)
os.makedirs(DEBUG_FOLDER, exist_ok=True)

# =====================================================
# CARI FILE GAMBAR
# =====================================================

exts = (".jpg", ".jpeg", ".png", ".bmp", ".tif", ".tiff")

image_files = sorted([
    f for f in os.listdir(INPUT_FOLDER)
    if f.lower().endswith(exts)
])

print(f"Total gambar ditemukan: {len(image_files)}")

# =====================================================
# PROCESS
# =====================================================

ok_count = 0
fallback_count = 0
error_count = 0

for idx, file_name in enumerate(image_files, start=1):

    print(f"\n[{idx}/{len(image_files)}] {file_name}")

    img_path = os.path.join(INPUT_FOLDER, file_name)

    img = cv2.imread(img_path)

    if img is None:
        print("  ERROR: gambar tidak bisa dibaca")
        error_count += 1
        continue

    try:

        roi_gray, dbg = detect_palm_opencv(
            img,
            debug=True
        )

        # =================================================
        # SIMPAN ROI
        # =================================================

        roi_save_path = os.path.join(
            ROI_FOLDER,
            file_name
        )

        cv2.imwrite(
            roi_save_path,
            roi_gray
        )

        # =================================================
        # SIMPAN OVERLAY
        # =================================================

        overlay = dbg.get("debug_overlay")

        if overlay is not None:

            overlay_save_path = os.path.join(
                DEBUG_FOLDER,
                f"debug_{file_name}"
            )

            cv2.imwrite(
                overlay_save_path,
                overlay
            )

        # =================================================
        # STATUS
        # =================================================

        if dbg["fallback"]:
            fallback_count += 1
            print("  FALLBACK")
        else:
            ok_count += 1
            print("  OK")

    except Exception as e:

        error_count += 1

        print(f"  ERROR: {e}")

# =====================================================
# RINGKASAN
# =====================================================

print("\n" + "=" * 50)
print("SELESAI")
print("=" * 50)

print(f"Total gambar : {len(image_files)}")
print(f"OK           : {ok_count}")
print(f"Fallback     : {fallback_count}")
print(f"Error        : {error_count}")

print(f"\nROI disimpan ke     : {ROI_FOLDER}")
print(f"Overlay disimpan ke : {DEBUG_FOLDER}")