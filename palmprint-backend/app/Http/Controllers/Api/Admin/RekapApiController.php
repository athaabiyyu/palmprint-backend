<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Jadwal;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use Illuminate\Http\Request;

class RekapApiController extends Controller
{
    // GET /api/admin/rekap/kelas?prodi_id=&semester_id=
    public function kelasByProdi(Request $request)
    {
        $query = Kelas::with(['prodi', 'semester'])->orderBy('nama');

        if ($request->prodi_id) {
            $query->where('prodi_id', $request->prodi_id);
        }

        if ($request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }

        return response()->json($query->get());
    }

    // GET /api/admin/rekap/jadwal/{kelasId}
    public function jadwalByKelas($kelasId)
    {
        $jadwals = Jadwal::with(['mataKuliah', 'dosen'])
            ->where('kelas_id', $kelasId)
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json($jadwals);
    }

    // GET /api/admin/rekap?jadwal_id=&tanggal_dari=&tanggal_sampai=
    public function rekap(Request $request)
    {
        $request->validate([
            'jadwal_id'      => 'required|exists:jadwals,id',
            'tanggal_dari'   => 'nullable|date',
            'tanggal_sampai' => 'nullable|date',
        ]);

        $jadwal = Jadwal::with([
            'mataKuliah',
            'kelas.mahasiswas',
            'kelas.prodi.jurusan',
            'dosen',
            'semester',
        ])->findOrFail($request->jadwal_id);

        $query = SesiAbsensi::where('jadwal_id', $request->jadwal_id);
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
            'jadwal' => $jadwal,
            'sesis'  => $sesis,
            'rekap'  => $rekap,
        ]);
    }
}