import os
import urllib.request

import cv2
import mediapipe as mp
import numpy as np
from mediapipe.tasks import python as mp_python
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
from mediapipe.tasks.python.vision.core.vision_task_running_mode import VisionTaskRunningMode

# =============================================================================
# KONSTANTA
# =============================================================================

ROI_SIZE = 128  # diperkecil dari 200 → 128

# Offset quad (× mcp_len), diukur dari baris MCP (P5–P17)
# Tidak ada alignment → quad langsung mengikuti orientasi tangan
#
#   unit_h : arah horizontal sepanjang baris MCP (P17 → P5)
#   unit_v : tegak lurus ke bawah (arah wrist), 90° dari unit_h
#
#  [P17] ──── MCP line ──── [P5]
#    TL ─────────────────── TR   ← OFFSET_TOP di atas baris MCP (ke arah jari)
#    │                       │
#    │       [ROI AREA]      │
#    │                       │
#    BL ─────────────────── BR   ← OFFSET_BOTTOM di bawah baris MCP (ke arah wrist)
OFFSET_TOP    = 0.05   # tepat di garis biru MCP
OFFSET_BOTTOM = 0.85  # coba kurangi sedikit
OFFSET_LEFT   = 0.00   # hapus margin kiri
OFFSET_RIGHT  = 0.05   # geser sedikit ke kanan
WIDTH_SCALE   = 0.85

# =============================================================================
# INISIALISASI MODEL
# =============================================================================

_MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "hand_landmarker.task")
_MODEL_URL  = (
    "https://storage.googleapis.com/mediapipe-models/"
    "hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task"
)


def _ensure_model():
    if not os.path.exists(_MODEL_PATH):
        print("[roi_mediapipe] Downloading hand_landmarker.task (~9 MB)...")
        urllib.request.urlretrieve(_MODEL_URL, _MODEL_PATH)
        print(f"[roi_mediapipe] Model tersimpan: {_MODEL_PATH}")


_ensure_model()

_options = HandLandmarkerOptions(
    base_options=mp_python.BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode=VisionTaskRunningMode.IMAGE,
    num_hands=1,
    min_hand_detection_confidence=0.5,
    min_hand_presence_confidence=0.5,
    min_tracking_confidence=0.5,
)
_landmarker = HandLandmarker.create_from_options(_options)


# =============================================================================
# DETEKSI MEDIAPIPE
# =============================================================================

def _detect(img_bgr: np.ndarray):
    """
    Deteksi landmark tangan.
    Jika gagal, retry dengan flip horizontal (workaround MediaPipe handedness).
    """
    if img_bgr.dtype != np.uint8:
        img_8bit = cv2.normalize(img_bgr, None, 0, 255, cv2.NORM_MINMAX, dtype=cv2.CV_8U)
    else:
        img_8bit = img_bgr.copy()
    if len(img_8bit.shape) == 2:
        img_8bit = cv2.cvtColor(img_8bit, cv2.COLOR_GRAY2BGR)

    img_rgb  = cv2.cvtColor(img_8bit, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=img_rgb)
    result   = _landmarker.detect(mp_image)

    if not result.hand_landmarks:
        flipped    = cv2.flip(img_8bit, 1)
        mp_flipped = mp.Image(image_format=mp.ImageFormat.SRGB,
                               data=cv2.cvtColor(flipped, cv2.COLOR_BGR2RGB))
        result_f   = _landmarker.detect(mp_flipped)
        if result_f.hand_landmarks:
            lm_orig = result_f.hand_landmarks[0]
            class _LM:
                def __init__(self, x, y, z): self.x = x; self.y = y; self.z = z
            mirrored = [_LM(1.0 - lm.x, lm.y, lm.z) for lm in lm_orig]
            class _FakeResult:
                def __init__(self, lms): self.hand_landmarks = [lms]
            return _FakeResult(mirrored)

    return result


# =============================================================================
# BANGUN QUAD (tanpa alignment)
# =============================================================================

def _build_quad(lm, w: int, h: int) -> np.ndarray:
    """
    Bangun quad langsung dari landmark asli (no rotation).

    Strategi anchor:
      PRIMARY  : P5  (telunjuk MCP) & P17 (kelingking MCP)
      FALLBACK : P9  (jari tengah MCP) & P13 (jari manis MCP)
                 dipakai bila P5/P17 tidak masuk akal (terlalu ke bawah / terlalu dekat)

    Untuk tangan kiri (dataset TJI, jari ke atas):
      - P5  ada di KIRI  (x lebih kecil)
      - P17 ada di KANAN (x lebih besar)
    Bila kebalik (MediaPipe salah handedness) → auto-swap.
    """
    p5x,  p5y  = lm[5].x  * w, lm[5].y  * h
    p17x, p17y = lm[17].x * w, lm[17].y * h
    p9x,  p9y  = lm[9].x  * w, lm[9].y  * h
    p13x, p13y = lm[13].x * w, lm[13].y * h

    # ── Tentukan left_pt dan right_pt berdasarkan posisi x ──────────────────
    # Untuk tangan kiri TJI: anchor kiri = P5 atau P17 yang x-nya lebih kecil
    if p5x <= p17x:
        left_pt  = np.array([p5x,  p5y],  dtype=np.float32)
        right_pt = np.array([p17x, p17y], dtype=np.float32)
    else:
        # hand_02, hand_10: P5 dan P17 kebalik dari MediaPipe
        left_pt  = np.array([p17x, p17y], dtype=np.float32)
        right_pt = np.array([p5x,  p5y],  dtype=np.float32)

    # ── SANITY CHECK: deteksi landmark terbalik / tidak masuk akal ──────────
    # Cek rata-rata y semua 4 anchor (P5, P9, P13, P17)
    all_mcp_y    = (p5y + p9y + p13y + p17y) / 4
    mcp_center_y = (left_pt[1] + right_pt[1]) / 2
    mcp_len_check = np.linalg.norm(right_pt - left_pt)

    # Landmark terbalik vertikal (hand_03): semua MCP y > 60% tinggi gambar
    # Normal ratio: 0.35-0.43 | hand_03 (terbalik): ~0.80
    landmark_flipped  = all_mcp_y > h * 0.60
    landmark_abnormal = (
        mcp_len_check < w * 0.10 or
        mcp_len_check > w * 0.80
    )

    if landmark_flipped or landmark_abnormal:
        # Landmark tidak bisa dipercaya → return None agar caller pakai center crop
        return None

    # ── FALLBACK ANCHOR: pakai P9/P13 bila P5/P17 terlalu ke bawah ─────────
    use_p9p13 = mcp_center_y > h * 0.55
    if use_p9p13:
        if p9x <= p13x:
            left_pt  = np.array([p9x,  p9y],  dtype=np.float32)
            right_pt = np.array([p13x, p13y], dtype=np.float32)
        else:
            left_pt  = np.array([p13x, p13y], dtype=np.float32)
            right_pt = np.array([p9x,  p9y],  dtype=np.float32)
    
    vec_mcp      = right_pt - left_pt        # vektor dari kiri → kanan
    mcp_len_orig = np.linalg.norm(vec_mcp)   # panjang asli untuk height

    if mcp_len_orig < 1e-6:
        pts_all = np.array([[lm[i].x * w, lm[i].y * h] for i in range(21)])
        xs, ys = pts_all[:, 0], pts_all[:, 1]
        return np.array([
            [xs.min(), ys.min()], [xs.max(), ys.min()],
            [xs.max(), ys.max()], [xs.min(), ys.max()],
        ], dtype=np.float32)

    unit_h = vec_mcp / mcp_len_orig

    # Scale down lebar saja, tinggi tetap pakai mcp_len_orig
    scaled_len = mcp_len_orig * WIDTH_SCALE
    center_pt  = (left_pt + right_pt) / 2
    left_pt    = center_pt - unit_h * (scaled_len / 2)
    right_pt   = center_pt + unit_h * (scaled_len / 2)
    mcp_len    = scaled_len   # untuk OFFSET_LEFT/RIGHT saja

    unit_v = np.array([unit_h[1], -unit_h[0]], dtype=np.float32)

    p0y = lm[0].y * h
    mcp_center_y = (left_pt[1] + right_pt[1]) / 2
    if unit_v[1] < 0 and p0y > mcp_center_y:
        unit_v = -unit_v

    # mcp_len_orig untuk arah vertikal, mcp_len (scaled) untuk horizontal
    tl = left_pt  - unit_v * (OFFSET_TOP    * mcp_len_orig) - unit_h * (OFFSET_LEFT  * mcp_len)
    tr = right_pt - unit_v * (OFFSET_TOP    * mcp_len_orig) + unit_h * (OFFSET_RIGHT * mcp_len)
    br = right_pt + unit_v * (OFFSET_BOTTOM * mcp_len_orig) + unit_h * (OFFSET_RIGHT * mcp_len)
    bl = left_pt  + unit_v * (OFFSET_BOTTOM * mcp_len_orig) - unit_h * (OFFSET_LEFT  * mcp_len)

    quad = np.array([tl, tr, br, bl], dtype=np.float32)
    quad[:, 0] = np.clip(quad[:, 0], 0, w - 1)
    quad[:, 1] = np.clip(quad[:, 1], 0, h - 1)
    return quad   # urutan: TL, TR, BR, BL


# =============================================================================
# PERSPECTIVE WARP
# =============================================================================

def _perspective_warp(img_bgr: np.ndarray, quad: np.ndarray,
                       out_size: int = ROI_SIZE) -> np.ndarray:
    dst = np.array([
        [0,          0         ],
        [out_size-1, 0         ],
        [out_size-1, out_size-1],
        [0,          out_size-1],
    ], dtype=np.float32)
    M_p = cv2.getPerspectiveTransform(quad, dst)
    return cv2.warpPerspective(img_bgr, M_p, (out_size, out_size),
                               flags=cv2.INTER_LINEAR,
                               borderMode=cv2.BORDER_REPLICATE)


# =============================================================================
# DEBUG OVERLAY
# =============================================================================

_HAND_CONNECTIONS = [
    (0,1),(1,2),(2,3),(3,4),
    (0,5),(5,6),(6,7),(7,8),
    (0,9),(9,10),(10,11),(11,12),
    (0,13),(13,14),(14,15),(15,16),
    (0,17),(17,18),(18,19),(19,20),
    (5,9),(9,13),(13,17),
]

def draw_debug_overlay(img_bgr: np.ndarray, lm, quad: np.ndarray | None,
                        w: int, h: int, fallback: bool = False) -> np.ndarray:
    out = img_bgr.copy()

    if fallback or lm is None:
        cv2.putText(out, "FALLBACK: tangan tidak terdeteksi",
                    (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)
        return out

    # Skeleton (abu-abu)
    for a, b in _HAND_CONNECTIONS:
        pa = (int(lm[a].x * w), int(lm[a].y * h))
        pb = (int(lm[b].x * w), int(lm[b].y * h))
        cv2.line(out, pa, pb, (90, 90, 90), 1, cv2.LINE_AA)

    # Semua landmark
    for i in range(21):
        pt = (int(lm[i].x * w), int(lm[i].y * h))
        cv2.circle(out, pt, 2, (200, 200, 200), -1, cv2.LINE_AA)

    # Garis MCP P5–P17 (biru)
    p5_pt  = (int(lm[5].x  * w), int(lm[5].y  * h))
    p17_pt = (int(lm[17].x * w), int(lm[17].y * h))
    cv2.line(out, p17_pt, p5_pt, (255, 120, 0), 2, cv2.LINE_AA)

    # Landmark kunci
    KEY = [
        (0,  (0,  50, 255), "P0"),
        (5,  (0,  220, 20), "P5"),
        (9,  (0,  200, 200),"P9"),
        (13, (255,140,  0), "P13"),
        (17, (180,  0, 220),"P17"),
    ]
    for idx, color, lbl in KEY:
        pt = (int(lm[idx].x * w), int(lm[idx].y * h))
        cv2.circle(out, pt, 6, color, -1, cv2.LINE_AA)
        cv2.circle(out, pt, 6, (0, 0, 0), 1)
        cv2.putText(out, lbl, (pt[0]+5, pt[1]-3),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.30, color, 1, cv2.LINE_AA)

    # Quad (hijau)
    if quad is not None:
        qi = quad.astype(np.int32)
        cv2.polylines(out, [qi], isClosed=True, color=(0, 255, 0), thickness=2)
        for i, lbl in enumerate(["TL", "TR", "BR", "BL"]):
            pt = tuple(qi[i])
            cv2.circle(out, pt, 4, (0, 255, 0), -1)
            cv2.putText(out, lbl, (pt[0]+3, pt[1]-3),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.28, (0, 255, 0), 1, cv2.LINE_AA)

    cv2.putText(out,
                f"NO ALIGN | TOP={OFFSET_TOP:.2f} BOT={OFFSET_BOTTOM:.2f} "
                f"L={OFFSET_LEFT:.2f} R={OFFSET_RIGHT:.2f}",
                (5, 14), cv2.FONT_HERSHEY_SIMPLEX, 0.33, (0, 255, 255), 1, cv2.LINE_AA)
    return out


# =============================================================================
# PUBLIC API
# =============================================================================

def extract_roi(img_bgr: np.ndarray) -> np.ndarray:
    """Terima BGR, kembalikan ROI 128×128 grayscale. Raises ValueError jika fallback."""
    roi_gray, dbg = detect_palm_opencv(img_bgr, debug=False)
    if dbg["fallback"]:
        raise ValueError("Tangan tidak terdeteksi oleh MediaPipe.")
    return roi_gray


def detect_palm_opencv(img_bgr: np.ndarray, debug: bool = False):
    """
    Pipeline ROI extraction palmprint (tanpa alignment).

    Returns:
        roi_gray : np.ndarray (128, 128) uint8 grayscale
        dbg      : dict — quad, lm, fallback, debug_overlay (jika debug=True)
    """
    h, w   = img_bgr.shape[:2]
    result = _detect(img_bgr)

    if not result.hand_landmarks:
        # Fallback: crop tengah gambar
        gray   = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
        half   = ROI_SIZE // 2
        cx, cy = w // 2, h // 2
        patch  = gray[max(cy-half, 0):cy+half, max(cx-half, 0):cx+half]
        dbg = {"quad": None, "lm": None, "fallback": True}
        if debug:
            dbg["debug_overlay"] = draw_debug_overlay(img_bgr, None, None, w, h, fallback=True)
        return cv2.resize(patch, (ROI_SIZE, ROI_SIZE)), dbg

    lm = result.hand_landmarks[0]

    # Bangun quad langsung dari landmark asli (no rotation)
    quad = _build_quad(lm, w, h)

    # Jika landmark tidak bisa dipercaya (terbalik/abnormal) → center crop
    if quad is None:
        gray  = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
        half  = ROI_SIZE // 2
        cx, cy = w // 2, h // 2
        patch = gray[max(cy-half, 0):cy+half, max(cx-half, 0):cx+half]
        dbg = {"quad": None, "lm": lm, "fallback": True}
        if debug:
            dbg["debug_overlay"] = draw_debug_overlay(
                img_bgr, lm, None, w, h, fallback=True)
        return cv2.resize(patch, (ROI_SIZE, ROI_SIZE)), dbg

    # Perspective warp
    roi_bgr  = _perspective_warp(img_bgr, quad, out_size=ROI_SIZE)
    roi_gray = cv2.cvtColor(roi_bgr, cv2.COLOR_BGR2GRAY)

    dbg = {"quad": quad, "lm": lm, "fallback": False}
    if debug:
        dbg["debug_overlay"] = draw_debug_overlay(img_bgr, lm, quad, w, h)

    return roi_gray, dbg


if __name__ == "__main__":
    pass