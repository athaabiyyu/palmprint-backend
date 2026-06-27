"""
FastAPI entry point untuk palmprint ML service.
"""
from fastapi import Form
import json
from typing import List

from fastapi import FastAPI, File, Form, UploadFile, HTTPException
from fastapi.responses import JSONResponse
import uvicorn
import traceback

from app.services.extractor import extract_from_roi, extract_from_roi_batch_register, identify_from_roi

# =====================================================================
# INISIALISASI SERVICE
# =====================================================================
app = FastAPI(
    title="Palmprint ML Service",
    description="Internal service untuk ekstraksi fitur palmprint terintegrasi Laravel.",
    version="2.0.0",
    docs_url="/docs",
    redoc_url=None,
)

ALLOWED_CONTENT_TYPES = ("image/jpeg", "image/png", "image/jpg")

# Jumlah file yang dianggap "mode registrasi" (3 foto asli -> 21 vector)
REGISTER_FILE_COUNT = 3


# =====================================================================
# HEALTH MONITORING
# =====================================================================
@app.get("/health")
def health_check():
    return {"status": "ok", "service": "palmprint-ml"}


# =====================================================================
# ENDPOINT UTAMA — EXTRACT VEKTOR PCA
# =====================================================================
@app.post("/extract")
async def extract_features(roi: List[UploadFile] = File(...)):
    """
    Mode otomatis berdasarkan jumlah file yang dikirim:
      - 1 file  -> mode absensi/verifikasi -> 1 vector
      - 3 file  -> mode registrasi -> tiap foto di-expand jadi 7 vector
                   (1 asli + 6 augmented) -> total 21 vector

    Laravel cukup attach field 'roi' berkali-kali dalam 1 multipart request
    (1x untuk absensi, 3x untuk registrasi/re-registrasi).
    """
    if not roi:
        raise HTTPException(status_code=422, detail="Tidak ada file yang dikirim.")

    # Validasi MIME type semua file
    for f in roi:
        if f.content_type not in ALLOWED_CONTENT_TYPES:
            raise HTTPException(
                status_code=422,
                detail=f"Tipe file '{f.content_type}' ditolak. Gunakan format JPG atau PNG."
            )

    # Baca semua bytes
    roi_bytes_list = []
    for f in roi:
        content = await f.read()
        if len(content) == 0:
            raise HTTPException(status_code=422, detail=f"Payload file '{f.filename}' kosong.")
        roi_bytes_list.append(content)

    try:
        if len(roi_bytes_list) == 1:
            # MODE ABSENSI: 1 foto -> 1 vector
            result = extract_from_roi(roi_bytes_list[0])
            return JSONResponse(content=result)

        elif len(roi_bytes_list) == REGISTER_FILE_COUNT:
            # MODE REGISTRASI: 3 foto -> 21 vector
            result = extract_from_roi_batch_register(roi_bytes_list)
            return JSONResponse(content=result)

        else:
            raise HTTPException(
                status_code=422,
                detail=f"Jumlah file tidak didukung: {len(roi_bytes_list)}. "
                       f"Kirim 1 file (absensi) atau {REGISTER_FILE_COUNT} file (registrasi)."
            )

    except ValueError as e:
        # Kasus: Gambar kena saring Quality Gate (tetap kembalikan HTTP 200 agar mudah diparsing PHP)
        return JSONResponse(
            status_code=200,
            content={"status": "error", "message": str(e)}
        )

    except RuntimeError as e:
        # Kasus: File corrupt / gagal dekompresi opencv
        return JSONResponse(
            status_code=400,
            content={"status": "error", "message": f"Dekompresi gagal: {str(e)}"}
        )

    except HTTPException:
        raise

    except Exception as e:
        # Proteksi server crash unhandled error
        traceback.print_exc()
        raise HTTPException(
            status_code=500,
            detail=f"Kesalahan internal pada ML Service: {str(e)}"
        )

# =====================================================================
# ENDPOINT 1:N — IDENTIFIKASI LANGSUNG TANPA USER_ID
# =====================================================================
@app.post("/identify")
async def identify_user(
    roi: UploadFile = File(...),
    gallery: UploadFile = File(...),  # ← gallery sebagai file JSON
):
    print(">>> IDENTIFY CALLED <<<")

    roi_bytes = await roi.read()
    gallery_bytes = await gallery.read()

    print(f">>> roi size={len(roi_bytes)}, gallery size={len(gallery_bytes)}")

    if roi.content_type not in ALLOWED_CONTENT_TYPES:
        raise HTTPException(status_code=422, detail=f"Tipe file ditolak: {roi.content_type}")

    if len(roi_bytes) == 0:
        raise HTTPException(status_code=422, detail="File roi kosong.")

    try:
        gallery_parsed = json.loads(gallery_bytes.decode('utf-8'))
        print(f">>> gallery parsed OK, len={len(gallery_parsed)}")
    except Exception as e:
        print(f">>> gallery parse ERROR: {e}")
        raise HTTPException(status_code=400, detail=f"Format gallery tidak valid: {e}")

    if not gallery_parsed:
        raise HTTPException(status_code=422, detail="Gallery kosong.")

    try:
        result = identify_from_roi(roi_bytes, gallery_parsed)
        return JSONResponse(content=result)
    except ValueError as e:
        return JSONResponse(status_code=200, content={"status": "error", "message": str(e)})
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))


if __name__ == "__main__":
    uvicorn.run("app.main:app", host="0.0.0.0", port=8001, reload=True)