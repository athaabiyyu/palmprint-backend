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
     * Kirim ROI image ke FastAPI untuk ekstraksi fitur.
     *
     * Format input:
     *   $imagePaths = ['/path/foto1.jpg']                              ← absensi (1 foto)
     *   $imagePaths = ['/path/foto1.jpg', '/path/foto2.jpg', '/path/foto3.jpg'] ← registrasi (3 foto)
     *
     * Format output:
     *   [['status' => 'success', 'vector' => [...], 'threshold' => 0.16, 'dim' => 588]]
     *   [['status' => 'error', 'message' => '...']]
     */
    public static function extractFeatures(array $imagePaths): ?array
    {
        $results = [];

        foreach ($imagePaths as $imagePath) {
            if (!file_exists($imagePath)) {
                Log::error("[PythonHelper] File tidak ditemukan: {$imagePath}");
                $results[] = [
                    'status'  => 'error',
                    'message' => "File tidak ditemukan: {$imagePath}",
                ];
                continue;
            }

            try {
                $response = Http::timeout(30)
                    ->attach(
                        'roi',
                        file_get_contents($imagePath),
                        basename($imagePath)
                    )
                    ->post(self::$FASTAPI_URL . '/extract');

                Log::info("[PythonHelper] FastAPI response status: " . $response->status());
                Log::info("[PythonHelper] FastAPI response body: " . $response->body());

                // ── Cek HTTP error (500, 422, dll) ──
                if ($response->failed()) {
                    $results[] = [
                        'status'  => 'error',
                        'message' => 'FastAPI error: HTTP ' . $response->status(),
                    ];
                    continue;
                }

                // ── Parse JSON response ──
                $data = $response->json();

                if (!$data || !isset($data['status'])) {
                    $results[] = [
                        'status'  => 'error',
                        'message' => 'Response FastAPI tidak valid',
                    ];
                    continue;
                }

                // ── Cek status field ──
                if ($data['status'] !== 'success') {
                    $results[] = [
                        'status'  => 'error',
                        'message' => $data['message'] ?? 'Gagal memproses foto',
                        'type'    => $data['type']    ?? 'quality_gate',
                    ];
                    continue;
                }

                $results[] = $data;

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error("[PythonHelper] FastAPI tidak bisa diakses: " . $e->getMessage());
                $results[] = [
                    'status'  => 'error',
                    'message' => 'ML service tidak tersedia. Coba beberapa saat lagi.',
                ];
            } catch (\Exception $e) {
                Log::error("[PythonHelper] Error tidak terduga: " . $e->getMessage());
                $results[] = [
                    'status'  => 'error',
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ];
            }
        }

        if (empty($results)) return null;

        return $results;
    }

    /**
     * Hitung cosine similarity antara dua vektor.
     *
     * Digunakan saat absensi untuk membandingkan:
     *   - query vector (dari foto absensi)
     *   - template vector (dari database, hasil registrasi)
     *
     * Mengembalikan nilai 0.0–1.0, makin tinggi makin mirip.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            Log::warning("[PythonHelper] Dimensi vektor tidak sama: " . count($a) . " vs " . count($b));
            return 0.0;
        }

        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot   += $val * ($b[$i] ?? 0);
            $normA += $val * $val;
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        if ($normA == 0 || $normB == 0) return 0.0;

        return $dot / (sqrt($normA) * sqrt($normB));
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