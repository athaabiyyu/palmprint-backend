"""
FastAPI entry point untuk palmprint ML service.
"""

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
import uvicorn

from app.services.extractor import extract_from_roi

# =====================================================================
# INISIALISASI APP
# =====================================================================

app = FastAPI(
    title="Palmprint ML Service",
    description="Internal service untuk ekstraksi fitur palmprint. Hanya untuk Laravel.",
    version="1.0.0",
    # docs hanya aktif di development — matikan di production
    docs_url="/docs",
    redoc_url=None,
)

# =====================================================================
# HEALTH CHECK
# =====================================================================

@app.get("/health")
def health_check():
    """
    Endpoint untuk cek apakah service hidup.
    Laravel bisa ping ini sebelum forward request.

    Response:
      {"status": "ok", "service": "palmprint-ml"}
    """
    return {"status": "ok", "service": "palmprint-ml"}


# =====================================================================
# ENDPOINT UTAMA — Extract Fitur dari ROI
# =====================================================================

@app.post("/extract")
async def extract_features(roi: UploadFile = File(...)):
    """
    Terima ROI image dari Laravel, return vektor fitur PCA.

    Input:
      - roi: file image (JPG/PNG), ukuran 200×200 grayscale
             dikirim sebagai multipart/form-data

    Output (success):
      {
        "status"    : "success",
        "vector"    : [0.123, -0.456, ...],  ← N float (dim PCA)
        "threshold" : 0.7234,
        "dim"       : N
      }

    Output (error):
      {
        "status"  : "error",
        "message" : "..."
      }

    Kenapa tidak return HTTP error code untuk quality gate:
      Laravel perlu tahu bedanya "server error" vs "foto jelek".
      Keduanya return 200 tapi status field beda — lebih mudah
      di-handle di PHP tanpa try-catch nested.
    """
    # ── Validasi tipe file ──
    if roi.content_type not in ("image/jpeg", "image/png", "image/jpg"):
        raise HTTPException(
            status_code=422,
            detail=f"Tipe file tidak valid: {roi.content_type}. Gunakan JPG atau PNG."
        )

    # ── Baca bytes dari upload ──
    roi_bytes = await roi.read()

    if len(roi_bytes) == 0:
        raise HTTPException(status_code=422, detail="File kosong.")

    # ── Jalankan pipeline ML ──
    try:
        result = extract_from_roi(roi_bytes)
        return JSONResponse(content=result)

    except ValueError as e:
        # Quality gate gagal atau model belum load — bukan server error
        return JSONResponse(
            content={"status": "error", "message": str(e)}
        )

    except RuntimeError as e:
        # Decode gagal — kemungkinan file corrupt
        return JSONResponse(
            status_code=400,
            content={"status": "error", "message": str(e)}
        )

    except Exception as e:
        # Error tidak terduga — log dan return 500
        raise HTTPException(status_code=500, detail=f"Internal error: {str(e)}")


# =====================================================================
# ENTRY POINT (kalau dijalankan langsung, bukan via uvicorn)
# =====================================================================

if __name__ == "__main__":
    uvicorn.run("app.main:app", host="0.0.0.0", port=8001, reload=True)