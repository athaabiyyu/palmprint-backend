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
            'senin'  => 'senin',  'selasa' => 'selasa',
            'rabu'   => 'rabu',   'kamis'  => 'kamis',
            'jumat'  => 'jumat',
        ];
        $hari = $hariMap[$hariIni] ?? 'senin';

        // Ambil jadwal kelas hari ini
        $jadwals = Jadwal::with(['mataKuliah', 'dosen', 'sesiAktif'])
            ->where('kelas_id', $kelas->id)
            ->where('hari', $hari)
            ->orderBy('jam_mulai')
            ->get()
            ->map(function ($j) use ($mahasiswa) {
                // Cek apakah mahasiswa sudah absen di sesi ini
                $sudahAbsen = false;
                if ($j->sesiAktif) {
                    $sudahAbsen = $j->sesiAktif->absensis()
                        ->where('mahasiswa_id', $mahasiswa->id)
                        ->exists();
                }
                $j->sudah_absen = $sudahAbsen;
                return $j;
            });

        return response()->json([
            'kelas' => $kelas->nama,
            'hari'  => $hari,
            'data'  => $jadwals,
        ]);
    }
}