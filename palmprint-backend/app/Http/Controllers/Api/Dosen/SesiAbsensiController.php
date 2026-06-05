<?php

namespace App\Http\Controllers\Api\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\SesiAbsensi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SesiAbsensiController extends Controller
{
    // Jadwal hari ini milik dosen
    public function jadwalHariIni(Request $request)
    {
        $hariIni = strtolower(Carbon::now()->locale('id')->dayName);

        $hariMap = [
            'senin'  => 'senin',
            'selasa' => 'selasa',
            'rabu'   => 'rabu',
            'kamis'  => 'kamis',
            'jumat'  => 'jumat',
        ];

        $hariSekarang = $hariMap[$hariIni] ?? null;

        // Ambil SEMUA jadwal dosen, bukan hanya hari ini
        $jadwals = Jadwal::with(['kelas', 'mataKuliah', 'sesiAktif'])
            ->where('dosen_id', $request->user()->id)
            ->orderByRaw("FIELD(hari, 'senin','selasa','rabu','kamis','jumat')")
            ->orderBy('jam_mulai')
            ->get()
            ->map(function ($j) use ($hariSekarang) {
                // Tambah flag is_today
                $j->is_today = ($j->hari === $hariSekarang);
                return $j;
            });

        return response()->json($jadwals);
    }

    // Buka sesi absensi
    public function buka(Request $request)
    {
        $request->validate([
            'jadwal_id'    => 'required|exists:jadwals,id',
            'durasi_menit' => 'required|integer|min:1|max:180',
        ]);

        // Cek apakah sudah ada sesi aktif untuk jadwal ini hari ini
        // $sudahAda = SesiAbsensi::where('jadwal_id', $request->jadwal_id)
        //     ->where('tanggal', Carbon::today())
        //     ->where('is_active', true)
        //     ->exists();
        $sudahAda = SesiAbsensi::where('jadwal_id', $request->jadwal_id)
            ->where('is_active', true)
            ->exists();

        if ($sudahAda) {
            return response()->json(['message' => 'Sesi absensi sudah aktif'], 422);
        }

        $sesi = SesiAbsensi::create([
            'jadwal_id'    => $request->jadwal_id,
            'tanggal'      => Carbon::today(),
            'dibuka_at'    => Carbon::now(),
            'durasi_menit' => $request->durasi_menit,
            'is_active'    => true,
        ]);

        return response()->json([
            'message' => 'Sesi absensi berhasil dibuka',
            'data'    => $sesi->load('jadwal.mataKuliah'),
        ], 201);
    }

    // Tutup sesi absensi
    public function tutup(Request $request, $id)
    {
        $sesi = SesiAbsensi::findOrFail($id);

        if (!$sesi->is_active) {
            return response()->json(['message' => 'Sesi sudah tidak aktif'], 422);
        }

        $sesi->update([
            'is_active'  => false,
            'ditutup_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Sesi absensi berhasil ditutup',
            'data'    => $sesi,
        ]);
    }

    // Detail sesi + daftar absensi mahasiswa
    public function detail($id)
    {
        $sesi = SesiAbsensi::with([
            'jadwal.mataKuliah',
            'jadwal.kelas.mahasiswas',
            'absensis.mahasiswa',
        ])->findOrFail($id);

        // Ambil semua mahasiswa di kelas
        $mahasiswas = $sesi->jadwal->kelas->mahasiswas;

        // Mahasiswa yang sudah absen
        $sudahAbsen = $sesi->absensis->pluck('mahasiswa_id')->toArray();

        // Tandai status tiap mahasiswa
        $daftarHadir = $mahasiswas->map(function ($m) use ($sudahAbsen, $sesi) {
            $absensi = $sesi->absensis->where('mahasiswa_id', $m->id)->first();
            return [
                'id'         => $m->id,
                'nim'        => $m->nim,
                'nama'       => $m->nama,
                'status'     => $absensi ? $absensi->status : 'belum',
                'waktu'      => $absensi ? $absensi->waktu_absen : null,
                'similarity' => $absensi ? $absensi->similarity_score : null,
            ];
        });

        return response()->json([
            'sesi'         => $sesi,
            'daftar_hadir' => $daftarHadir,
        ]);
    }

    // Cek sesi aktif (untuk Flutter mahasiswa)
    public function sesiAktif()
    {
        $sesiAktif = SesiAbsensi::with(['jadwal.mataKuliah', 'jadwal.kelas'])
            ->where('is_active', true)
            ->where('tanggal', Carbon::today())
            ->get()
            ->filter(function ($sesi) {
                // Cek apakah durasi belum habis
                $batasWaktu = Carbon::parse($sesi->dibuka_at)
                    ->addMinutes($sesi->durasi_menit);
                if (Carbon::now()->greaterThan($batasWaktu)) {
                    // Otomatis tutup kalau sudah lewat durasi
                    $sesi->update([
                        'is_active'  => false,
                        'ditutup_at' => $batasWaktu,
                    ]);
                    return false;
                }
                return true;
            })
            ->values();

        return response()->json($sesiAktif);
    }

    // ── Rekap kehadiran per MK milik dosen ──
    public function rekapMatkul(Request $request)
    {
        $dosen = $request->user();

        // Ambil semua jadwal milik dosen
        $jadwals = Jadwal::with(['mataKuliah', 'kelas', 'semester'])
            ->where('dosen_id', $dosen->id)
            ->whereHas('semester', fn($q) => $q->where('is_active', true))
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json($jadwals);
    }

    // ── Detail rekap per jadwal ──
    public function rekapDetail(Request $request, $jadwalId)
    {
        $dosen  = $request->user();
        $jadwal = Jadwal::with([
            'mataKuliah',
            'kelas.mahasiswas',
            'semester'
        ])->where('dosen_id', $dosen->id)
            ->findOrFail($jadwalId);

        $query = SesiAbsensi::where('jadwal_id', $jadwalId);
        if ($request->tanggal_dari)   $query->whereDate('tanggal', '>=', $request->tanggal_dari);
        if ($request->tanggal_sampai) $query->whereDate('tanggal', '<=', $request->tanggal_sampai);
        $sesis = $query->orderBy('tanggal')->get();

        $mahasiswas = $jadwal->kelas->mahasiswas;

        $rekap = $mahasiswas->map(function ($m) use ($sesis) {
            $detail = $sesis->map(function ($sesi) use ($m) {
                $absensi = Absensi::where('sesi_absensi_id', $sesi->id)
                    ->where('mahasiswa_id', $m->id)
                    ->first();
                return [
                    'tanggal' => $sesi->tanggal,
                    'status'  => $absensi ? $absensi->status : 'alpha',
                ];
            });

            $hadir = $detail->where('status', 'hadir')->count();
            $alpha = $detail->where('status', 'alpha')->count();
            $izin  = $detail->where('status', 'izin')->count();
            $sakit = $detail->where('status', 'sakit')->count();
            $total = $sesis->count();

            return [
                'nim'        => $m->nim,
                'nama'       => $m->nama,
                'hadir'      => $hadir,
                'alpha'      => $alpha,
                'izin'       => $izin,
                'sakit'      => $sakit,
                'total_sesi' => $total,
                'persentase' => $total > 0 ? round(($hadir / $total) * 100) : 0,
            ];
        });

        return response()->json([
            'jadwal' => $jadwal,
            'sesis'  => $sesis,
            'rekap'  => $rekap,
        ]);
    }

    // ── Riwayat sesi per jadwal ──
    public function riwayat(Request $request, $jadwalId)
    {
        $dosen  = $request->user();

        // Pastikan jadwal milik dosen ini
        $jadwal = Jadwal::with(['mataKuliah', 'kelas'])
            ->where('dosen_id', $dosen->id)
            ->findOrFail($jadwalId);

        $sesis = SesiAbsensi::where('jadwal_id', $jadwalId)
            ->withCount([
                'absensis as jumlah_hadir' => fn($q) => $q->where('status', 'hadir'),
                'absensis as jumlah_izin'  => fn($q) => $q->where('status', 'izin'),
                'absensis as jumlah_sakit' => fn($q) => $q->where('status', 'sakit'),
            ])
            ->orderByDesc('tanggal')
            ->get()
            ->map(function ($s) use ($jadwal) {
                $totalMahasiswa = $jadwal->kelas->mahasiswas()->count();
                return [
                    'id'              => $s->id,
                    'tanggal'         => $s->tanggal,
                    'dibuka_at'       => $s->dibuka_at,
                    'ditutup_at'      => $s->ditutup_at,
                    'durasi_menit'    => $s->durasi_menit,
                    'is_active'       => $s->is_active,
                    'jumlah_hadir'    => $s->jumlah_hadir,
                    'jumlah_izin'     => $s->jumlah_izin,
                    'jumlah_sakit'    => $s->jumlah_sakit,
                    'jumlah_alpha'    => $totalMahasiswa - $s->jumlah_hadir - $s->jumlah_izin - $s->jumlah_sakit,
                    'total_mahasiswa' => $totalMahasiswa,
                    'persentase'      => $totalMahasiswa > 0
                        ? round(($s->jumlah_hadir / $totalMahasiswa) * 100)
                        : 0,
                ];
            });

        return response()->json([
            'jadwal' => $jadwal,
            'sesis'  => $sesis,
        ]);
    }
}
