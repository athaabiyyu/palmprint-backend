<?php
namespace App\Http\Controllers\Api\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use App\Models\PalmprintTemplate;
use App\Helpers\PythonHelper;
use Illuminate\Http\Request;
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

        // Simpan foto sementara
        $fileName = uniqid() . '.' . $request->file('foto')->getClientOriginalExtension();
        $fullPath = storage_path('app/temp/' . $fileName);
        $request->file('foto')->move(storage_path('app/temp'), $fileName);

        // Ekstraksi fitur
        $result = PythonHelper::extractFeature($fullPath);
        if (file_exists($fullPath)) unlink($fullPath);

        if (!$result) {
            return response()->json(['message' => 'Gagal memproses foto'], 422);
        }

        $queryVector = $result['vector'];
        $threshold   = $result['threshold'];

        // Ambil template palmprint mahasiswa
        $templates = PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)->get();

        if ($templates->isEmpty()) {
            return response()->json(['message' => 'Template palmprint tidak ditemukan'], 422);
        }

        // Hitung cosine similarity
        $bestScore = 0;
        foreach ($templates as $template) {
            $storedVector = json_decode($template->feature_vector, true);
            $score        = PythonHelper::cosineSimilarity($queryVector, $storedVector);
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }

        if ($bestScore >= $threshold) {
            // Catat absensi
            Absensi::create([
                'sesi_absensi_id' => $sesi->id,
                'mahasiswa_id'    => $mahasiswa->id,
                'waktu_absen'     => Carbon::now(),
                'similarity_score'=> $bestScore,
                'status'          => 'hadir',
            ]);

            return response()->json([
                'message'    => 'Absensi berhasil!',
                'similarity' => $bestScore,
                'status'     => 'hadir',
            ]);
        }

        return response()->json([
            'message'    => 'Telapak tangan tidak dikenali',
            'similarity' => $bestScore,
        ], 401);
    }
}