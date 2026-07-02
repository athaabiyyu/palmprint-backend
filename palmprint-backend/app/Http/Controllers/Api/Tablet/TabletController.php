<?php

namespace App\Http\Controllers\Api\Tablet;

use App\Http\Controllers\Controller;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use App\Models\PalmprintTemplate;
use App\Helpers\PythonHelper;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TabletController extends Controller
{
    public function sesiAktif()
    {
        $now = Carbon::now();

        $sesis = SesiAbsensi::with([
            'jadwal.mataKuliah',
            'jadwal.dosen',
            'jadwal.kelas',
        ])
            ->where('is_active', true)
            ->get()
            ->filter(function ($sesi) use ($now) {
                $batas = Carbon::parse($sesi->dibuka_at)
                    ->addMinutes($sesi->durasi_menit);
                return $now->lessThan($batas);
            })
            ->values();

        return response()->json(['data' => $sesis]);
    }

    public function absensi(Request $request)
    {
        $request->validate([
            'sesi_absensi_id' => 'required|exists:sesi_absensis,id',
            'foto'            => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
        ]);

        $sesi = SesiAbsensi::find($request->sesi_absensi_id);
        if (!$sesi->is_active) {
            return response()->json(['message' => 'Sesi absensi sudah ditutup'], 422);
        }

        $batasWaktu = Carbon::parse($sesi->dibuka_at)->addMinutes($sesi->durasi_menit);
        if (Carbon::now()->greaterThan($batasWaktu)) {
            $sesi->update(['is_active' => false, 'ditutup_at' => $batasWaktu]);
            return response()->json(['message' => 'Waktu absensi sudah habis'], 422);
        }

        $modelVersion = config('palmprint.model_version');
        
        
        $mahasiswaIds = $sesi->jadwal->kelas->mahasiswas()->pluck('mahasiswas.id');
        $gallery = [];
        foreach ($mahasiswaIds as $mahasiswaId) {
            $vectors = PalmprintTemplate::where('mahasiswa_id', $mahasiswaId)
                ->where('model_version', $modelVersion)
                ->pluck('feature_vector')
                ->map(fn($v) => json_decode($v, true))
                ->values()
                ->toArray();

            if (count($vectors) > 0) {
                $gallery[] = ['user_id' => $mahasiswaId, 'vectors' => $vectors];
            }
        }

        if (empty($gallery)) {
            return response()->json([
                'message' => 'Tidak ada template palmprint terdaftar di kelas ini.',
            ], 422);
        }

        $fileName = uniqid() . '.' . $request->file('foto')->getClientOriginalExtension();
        $fullPath = storage_path('app/temp/' . $fileName);
        $request->file('foto')->move(storage_path('app/temp'), $fileName);

        $result = PythonHelper::identifyUser($fullPath, $gallery);
        if (file_exists($fullPath)) unlink($fullPath);


        if ($result['status'] === 'error') {
            return response()->json(['message' => $result['message'] ?? 'Gagal memproses foto'], 422);
        }

        if ($result['status'] === 'unknown') {
            return response()->json([
                'message'          => 'Telapak tangan tidak dikenali.',
                'score'            => round($result['score'], 4),
                'margin'           => round($result['margin'], 4),
                'threshold_sim'    => round($result['threshold_sim'], 4),
                'threshold_margin' => round($result['threshold_margin'], 4),
            ], 401);
        }

        $mahasiswa = \App\Models\Mahasiswa::find($result['user_id']);
        if (!$mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan.'], 422);
        }

        if (!$mahasiswaIds->contains($mahasiswa->id)) {
            return response()->json(['message' => 'Mahasiswa tidak terdaftar di kelas ini.'], 401);
        }

        $sudahAbsen = Absensi::where('sesi_absensi_id', $sesi->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->exists();

        if ($sudahAbsen) {
            return response()->json([
                'message'   => "{$mahasiswa->nama} sudah melakukan absensi.",
                'mahasiswa' => ['id' => $mahasiswa->id, 'nama' => $mahasiswa->nama],
            ], 422);
        }

        Absensi::create([
            'sesi_absensi_id'  => $sesi->id,
            'mahasiswa_id'     => $mahasiswa->id,
            'waktu_absen'      => Carbon::now(),
            'similarity_score' => $result['score'],
            'status'           => 'hadir',
        ]);

        return response()->json([
            'message'          => 'Absensi berhasil!',
            'mahasiswa'        => ['id' => $mahasiswa->id, 'nama' => $mahasiswa->nama, 'nim' => $mahasiswa->nim],
            'similarity'       => round($result['score'], 4),
            'margin'           => round($result['margin'], 4),
            'threshold_sim'    => round($result['threshold_sim'], 4),
            'threshold_margin' => round($result['threshold_margin'], 4),
            'status'           => 'hadir',
        ]);
    }
}