<?php

namespace App\Http\Controllers\Api\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use App\Models\PalmprintTemplate;
use App\Helpers\PythonHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AbsensiController extends Controller
{
    public function absensi(Request $request)
    {
        $request->validate([
            'sesi_absensi_id' => 'required|exists:sesi_absensis,id',
            'foto'            => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
        ]);

        $mahasiswa = $request->user();

        // Cek sesi masih aktif
        $sesi = SesiAbsensi::find($request->sesi_absensi_id);
        if (!$sesi->is_active) {
            return response()->json(['message' => 'Sesi absensi sudah ditutup'], 422);
        }

        // Cek durasi sesi belum habis
        $batasWaktu = Carbon::parse($sesi->dibuka_at)->addMinutes($sesi->durasi_menit);
        if (Carbon::now()->greaterThan($batasWaktu)) {
            $sesi->update(['is_active' => false, 'ditutup_at' => $batasWaktu]);
            return response()->json(['message' => 'Waktu absensi sudah habis'], 422);
        }

        // Cek mahasiswa belum absen
        $sudahAbsen = Absensi::where('sesi_absensi_id', $sesi->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->exists();

        if ($sudahAbsen) {
            return response()->json(['message' => 'Anda sudah melakukan absensi'], 422);
        }

        // ── CEK TEMPLATE VALID DULU (sebelum proses foto) ──
        $modelVersion = config('palmprint.model_version');
        $templates    = PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)
            ->where('model_version', $modelVersion)
            ->get();

        if ($templates->isEmpty()) {
            return response()->json([
                'message'             => config('palmprint.outdated_template_message'),
                'perlu_re_registrasi' => true,
            ], 422);
        }

        // ── SIMPAN FOTO SEMENTARA ──
        $fileName = uniqid() . '.' . $request->file('foto')->getClientOriginalExtension();
        $fullPath = storage_path('app/temp/' . $fileName);
        $request->file('foto')->move(storage_path('app/temp'), $fileName);

        // ── EKSTRAKSI FITUR ──
        $result = PythonHelper::extractFeatures([$fullPath]);
        if (file_exists($fullPath)) unlink($fullPath);

        if (!$result || $result[0]['status'] !== 'success') {
            return response()->json(['message' => 'Gagal memproses foto'], 422);
        }

        $queryVector = $result[0]['vector'];
        $threshold   = $result[0]['threshold'];
        $queryDim    = count($queryVector);

        // ── HITUNG COSINE SIMILARITY (MAX dari semua template) ──
        $bestScore    = 0;
        $bestTemplate = null;

        foreach ($templates as $template) {
            $storedVector = json_decode($template->feature_vector, true);
            $storedDim    = count($storedVector);

            // Validasi dimensi — harus sama
            if ($queryDim !== $storedDim) {
                Log::warning("Dimensi tidak cocok: query={$queryDim}, template={$storedDim}, template_id={$template->id}");
                continue;
            }

            $score = PythonHelper::cosineSimilarity($queryVector, $storedVector);
            if ($score > $bestScore) {
                $bestScore    = $score;
                $bestTemplate = $template;
            }
        }

        // ── DEBUG LOG ──
        Log::info('=== DEBUG ABSENSI ===');
        Log::info('Mahasiswa ID   : ' . $mahasiswa->id);
        Log::info('Model version  : ' . $modelVersion);
        Log::info('Query dim      : ' . $queryDim);
        Log::info('Threshold      : ' . $threshold);
        Log::info('Best score     : ' . $bestScore);
        Log::info('Jumlah tmpl    : ' . $templates->count());
        Log::info('Template dims  : ' . $templates->map(fn($t) => count(json_decode($t->feature_vector, true)))->join(', '));

        // ── MATCHING ──
        if ($bestScore >= $threshold) {
            Absensi::create([
                'sesi_absensi_id'  => $sesi->id,
                'mahasiswa_id'     => $mahasiswa->id,
                'waktu_absen'      => Carbon::now(),
                'similarity_score' => $bestScore,
                'status'           => 'hadir',
            ]);

            return response()->json([
                'message'    => 'Absensi berhasil!',
                'similarity' => round($bestScore, 4),
                'threshold'  => round($threshold, 4),
                'status'     => 'hadir',
            ]);
        }

        return response()->json([
            'message'    => 'Telapak tangan tidak dikenali. Pastikan pencahayaan cukup dan telapak tangan terlihat jelas.',
            'similarity' => round($bestScore, 4),
            'threshold'  => round($threshold, 4),
        ], 401);
    }
}