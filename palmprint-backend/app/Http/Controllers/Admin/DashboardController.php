<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Jadwal;
use App\Models\Semester;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use Carbon\Carbon;
use App\Models\Surat;

class DashboardController extends Controller
{
    public function index()
    {
        $suratPending = Surat::where('status', 'pending')->count();
        $semesterAktif = Semester::where('is_active', true)->first();
        $hariIni       = strtolower(Carbon::now()->locale('id')->dayName);
        $hariMap       = [
            'senin' => 'senin', 'selasa' => 'selasa', 'rabu' => 'rabu',
            'kamis' => 'kamis', 'jumat'  => 'jumat',
        ];
        $hari = $hariMap[$hariIni] ?? 'senin';

        // ── Summary Cards ──
        $totalMahasiswa = Mahasiswa::where('is_active', true)->count();
        $totalDosen     = Dosen::where('is_active', true)->count();
        $totalKelas     = $semesterAktif
            ? Kelas::where('semester_id', $semesterAktif->id)->count()
            : 0;
        $totalMatkul    = $semesterAktif
            ? MataKuliah::where('semester_id', $semesterAktif->id)->count()
            : 0;

        // ── Jadwal Hari Ini ──
        $jadwalHariIni = $semesterAktif
            ? Jadwal::with(['kelas', 'mataKuliah', 'dosen', 'sesiAktif'])
                ->where('semester_id', $semesterAktif->id)
                ->where('hari', $hari)
                ->orderBy('jam_mulai')
                ->get()
            : collect();

        // ── Sesi Absensi Aktif ──
        $sesiAktif = SesiAbsensi::with(['jadwal.mataKuliah', 'jadwal.dosen', 'jadwal.kelas'])
            ->where('is_active', true)
            ->get();

        // ── Mahasiswa Belum Palmprint ──
        $belumPalmprint = Mahasiswa::where('is_active', true)
            ->doesntHave('palmprintTemplates')
            ->count();

        // ── Grafik Kehadiran 7 Hari Terakhir ──
        $grafik = collect(range(6, 0))->map(function ($daysAgo) {
            $tanggal = Carbon::now()->subDays($daysAgo);
            $jumlah  = Absensi::whereDate('created_at', $tanggal)
                ->where('status', 'hadir')
                ->count();
            return [
                'label'  => $tanggal->format('d/m'),
                'jumlah' => $jumlah,
            ];
        });

        return view('admin.dashboard', compact(
            'semesterAktif',
            'totalMahasiswa',
            'totalDosen',
            'totalKelas',
            'totalMatkul',
            'jadwalHariIni',
            'sesiAktif',
            'belumPalmprint',
            'grafik',
            'hari',
            'suratPending',
        ));
    }
}