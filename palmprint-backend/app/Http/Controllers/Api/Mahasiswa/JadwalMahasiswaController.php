<?php

namespace App\Http\Controllers\Api\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\SesiAbsensi;
use Illuminate\Http\Request;
use Carbon\Carbon;

class JadwalMahasiswaController extends Controller
{
    public function jadwalHariIni(Request $request)
    {
        $mahasiswa = $request->user();

        // Ambil kelas mahasiswa
        $kelas = $mahasiswa->kelas()->first();

        if (!$kelas) {
            return response()->json([
                'message' => 'Anda belum terdaftar di kelas manapun',
                'data'    => [],
            ]);
        }

        $hariIni = strtolower(Carbon::now()->locale('id')->dayName);
        $hariMap = [
            'senin'  => 'senin',
            'selasa' => 'selasa',
            'rabu'   => 'rabu',
            'kamis'  => 'kamis',
            'jumat'  => 'jumat',
        ];
        $hari = $hariMap[$hariIni] ?? 'senin';

        // Ambil jadwal kelas hari ini
        $jadwals = Jadwal::with(['mataKuliah', 'dosen', 'sesiAktif'])
            ->where('kelas_id', $kelas->id)
            ->whereHas('semester', fn($q) => $q->where('is_active', true)) // ← tambah ini
            ->orderByRaw("FIELD(hari, 'senin','selasa','rabu','kamis','jumat')")
            ->orderBy('jam_mulai')
            ->get()
            ->map(function ($j) use ($hari, $mahasiswa) {
                $sesiAktif = $j->sesiAktif;

                $sudahAbsen = false;
                if ($sesiAktif) {
                    $sudahAbsen = $sesiAktif->absensis()
                        ->where('mahasiswa_id', $mahasiswa->id)
                        ->where('status', 'hadir')
                        ->exists();
                }

                $sisaDetik = null;
                if ($sesiAktif) {
                    $batas     = Carbon::parse($sesiAktif->dibuka_at)->addMinutes($sesiAktif->durasi_menit);
                    $sisaDetik = max(0, Carbon::now()->diffInSeconds($batas, false));

                    if ($sisaDetik <= 0) {
                        $sesiAktif->update(['is_active' => false, 'ditutup_at' => $batas]);
                        $j->sesi_aktif  = null;
                        $sisaDetik      = null;
                    }
                }

                // is_today tetap sesuai hari asli (untuk badge)
                $j->is_today    = ($j->hari === $hari);
                $j->sudah_absen = $sudahAbsen;
                $j->sisa_detik  = $sisaDetik;

                return $j;
            });

        return response()->json([
            'kelas' => $kelas->nama,
            'hari'  => $hari,
            'data'  => $jadwals,
        ]);
    }
}
