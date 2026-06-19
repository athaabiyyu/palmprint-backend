<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PythonHelper
{
    /**
     * URL FastAPI service — internal only, tidak expose ke publik.
     */
    private static string $FASTAPI_URL = 'http://127.0.0.1:8001';

    /**
     * MODE ABSENSI — kirim 1 foto, terima 1 vector.
     *
     * Format output sukses:
     *   ['status' => 'success', 'mode' => 'verify', 'vector' => [...], 'threshold' => 0.16, 'dim' => 588]
     * Format output gagal:
     *   ['status' => 'error', 'message' => '...']
     */
    public static function extractFeature(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            Log::error("[PythonHelper] File tidak ditemukan: {$imagePath}");
            return [
                'status'  => 'error',
                'message' => "File tidak ditemukan: {$imagePath}",
            ];
        }

        try {
            $response = Http::timeout(30)
                ->attach('roi', file_get_contents($imagePath), basename($imagePath))
                ->post(self::$FASTAPI_URL . '/extract');

            Log::info("[PythonHelper] FastAPI response status: " . $response->status());

            if ($response->failed()) {
                return [
                    'status'  => 'error',
                    'message' => 'FastAPI error: HTTP ' . $response->status(),
                ];
            }

            $data = $response->json();

            if (!$data || !isset($data['status'])) {
                return [
                    'status'  => 'error',
                    'message' => 'Response FastAPI tidak valid',
                ];
            }

            if ($data['status'] !== 'success') {
                return [
                    'status'  => 'error',
                    'message' => $data['message'] ?? 'Gagal memproses foto',
                    'type'    => $data['type']    ?? 'quality_gate',
                ];
            }

            return $data;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("[PythonHelper] FastAPI tidak bisa diakses: " . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'ML service tidak tersedia. Coba beberapa saat lagi.',
            ];
        } catch (\Exception $e) {
            Log::error("[PythonHelper] Error tidak terduga: " . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * MODE REGISTRASI — kirim 3 foto SEKALIGUS dalam 1 request multipart
     * (field 'roi' di-attach 3x). FastAPI akan expand tiap foto jadi
     * 1 asli + 6 augmented = 21 vector total.
     *
     * $imagePaths HARUS berisi tepat 3 path foto.
     *
     * Format output sukses:
     *   [
     *     'status' => 'success',
     *     'mode' => 'register',
     *     'vectors' => [ [...], [...], ... ],   // 21 array fitur
     *     'per_photo_count' => 7,
     *     'total_vectors' => 21,
     *     'threshold' => 0.16,
     *     'dim' => 588,
     *   ]
     * Format output gagal:
     *   ['status' => 'error', 'message' => '...']
     */
    public static function extractFeaturesForRegistration(array $imagePaths): array
    {
        foreach ($imagePaths as $path) {
            if (!file_exists($path)) {
                Log::error("[PythonHelper] File tidak ditemukan: {$path}");
                return [
                    'status'  => 'error',
                    'message' => "File tidak ditemukan: {$path}",
                ];
            }
        }

        try {
            $request = Http::timeout(60); // lebih lama: 3 foto x 7 vector = lebih berat dari mode absensi

            foreach ($imagePaths as $path) {
                $request = $request->attach('roi', file_get_contents($path), basename($path));
            }

            $response = $request->post(self::$FASTAPI_URL . '/extract');

            Log::info("[PythonHelper] FastAPI register response status: " . $response->status());

            if ($response->failed()) {
                return [
                    'status'  => 'error',
                    'message' => 'FastAPI error: HTTP ' . $response->status(),
                ];
            }

            $data = $response->json();

            if (!$data || !isset($data['status'])) {
                return [
                    'status'  => 'error',
                    'message' => 'Response FastAPI tidak valid',
                ];
            }

            if ($data['status'] !== 'success') {
                return [
                    'status'  => 'error',
                    'message' => $data['message'] ?? 'Gagal memproses foto',
                    'type'    => $data['type']    ?? 'quality_gate',
                ];
            }

            if (!isset($data['vectors']) || !is_array($data['vectors'])) {
                return [
                    'status'  => 'error',
                    'message' => 'Response FastAPI tidak mengandung vectors',
                ];
            }

            return $data;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("[PythonHelper] FastAPI tidak bisa diakses: " . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'ML service tidak tersedia. Coba beberapa saat lagi.',
            ];
        } catch (\Exception $e) {
            Log::error("[PythonHelper] Error tidak terduga: " . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Hitung cosine similarity antara dua vektor (PCA Space).
     * SINKRON 100% dengan perhitungan matematis scikit-learn Python.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $countA = count($a);
        $countB = count($b);

        if ($countA !== $countB) {
            Log::warning("[PythonHelper] Dimensi vektor tidak sama: " . $countA . " vs " . $countB);
            return -1.0; // Kembalikan nilai batas paling tidak mirip jika dimensi rusak
        }

        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $countA; $i++) {
            $valA = (float)$a[$i];
            $valB = (float)$b[$i];

            $dot   += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA == 0 || $normB == 0) {
            return -1.0;
        }

        $similarity = $dot / (sqrt($normA) * sqrt($normB));

        if ($similarity > 1.0) $similarity = 1.0;
        if ($similarity < -1.0) $similarity = -1.0;

        return $similarity;
    }

    /**
     * Cek apakah FastAPI service sedang jalan.
     */
    public static function isServiceAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get(self::$FASTAPI_URL . '/health');
            return $response->ok();
        } catch (\Exception $e) {
            return false;
        }
    }
}