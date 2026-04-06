<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Jadwal;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RekapApiController extends Controller
{
    // Ambil jadwal berdasarkan kelas
    public function jadwalByKelas($kelasId)
    {
        $jadwals = Jadwal::with(['mataKuliah', 'dosen'])
            ->where('kelas_id', $kelasId)
            ->get();

        return response()->json($jadwals);
    }

    // Rekap absensi per jadwal
    public function rekap(Request $request)
    {
        $request->validate([
            'jadwal_id'  => 'required|exists:jadwals,id',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date',
        ]);

        $jadwal = Jadwal::with(['mataKuliah', 'kelas.mahasiswas'])->findOrFail($request->jadwal_id);

        // Ambil semua sesi absensi jadwal ini
        $query = SesiAbsensi::where('jadwal_id', $request->jadwal_id);

        if ($request->tanggal_dari) {
            $query->whereDate('tanggal', '>=', $request->tanggal_dari);
        }
        if ($request->tanggal_sampai) {
            $query->whereDate('tanggal', '<=', $request->tanggal_sampai);
        }

        $sesis = $query->orderBy('tanggal')->get();

        // Ambil semua mahasiswa di kelas
        $mahasiswas = $jadwal->kelas->mahasiswas;

        // Buat rekap per mahasiswa
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
                'id'         => $m->id,
                'nim'        => $m->nim,
                'nama'       => $m->nama,
                'hadir'      => $hadir,
                'alpha'      => $alpha,
                'izin'       => $izin,
                'sakit'      => $sakit,
                'total_sesi' => $total,
                'persentase' => $total > 0 ? round(($hadir / $total) * 100) : 0,
                'detail'     => $detail,
            ];
        });

        return response()->json([
            'jadwal'    => $jadwal,
            'sesis'     => $sesis,
            'rekap'     => $rekap,
        ]);
    }
}