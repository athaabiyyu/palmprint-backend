<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    // ── GET /api/admin/jadwals?semester_id=&kelas_id= ──
    public function index(Request $request)
    {
        $query = Jadwal::with([
            'semester',
            'kelas.prodi.jurusan',
            'mataKuliah.prodi',
            'dosen'
        ])->latest();

        if ($request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->kelas_id) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->prodi_id) {
            $query->whereHas('kelas', fn($q) => $q->where('prodi_id', $request->prodi_id));
        }

        return response()->json($query->get());
    }
    // ── GET /api/admin/jadwals/kelas/{kelasId} ──
    public function byKelas($kelasId)
    {
        $jadwals = Jadwal::with(['semester', 'mataKuliah', 'dosen'])
            ->where('kelas_id', $kelasId)
            ->orderBy('hari')           // ← dari kode lama, lebih rapi
            ->orderBy('jam_mulai')      // ← dari kode lama, lebih rapi
            ->get();

        return response()->json($jadwals);
    }

    // ── POST /api/admin/jadwals ──
    public function store(Request $request)
    {
        $request->validate([
            'semester_id'    => 'required|exists:semesters,id',
            'kelas_id'       => 'required|exists:kelas,id',
            'mata_kuliah_id' => 'required|exists:mata_kuliahs,id',
            'dosen_id'       => 'required|exists:dosens,id',
            'hari'           => 'required|in:senin,selasa,rabu,kamis,jumat',
            'jam_mulai'      => 'required|date_format:H:i',       // ← dari kode lama
            'jam_selesai'    => 'required|date_format:H:i|after:jam_mulai', // ← dari kode lama
            'ruangan'        => 'nullable|string|max:100',
        ]);

        $request->merge([
            'jam_mulai'  => \Carbon\Carbon::createFromFormat('H:i', substr($request->jam_mulai, 0, 5))->format('H:i'),
            'jam_selesai' => \Carbon\Carbon::createFromFormat('H:i', substr($request->jam_selesai, 0, 5))->format('H:i'),
        ]);

        $bentrok = $this->cekBentrok($request);
        if ($bentrok) {
            return response()->json(['message' => $bentrok], 422);
        }

        $jadwal = Jadwal::create($request->only([
            'semester_id',
            'kelas_id',
            'mata_kuliah_id',
            'dosen_id',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'ruangan'
        ]));

        return response()->json([
            'message' => 'Jadwal berhasil dibuat',
            'data'    => $jadwal->load(['semester', 'kelas', 'mataKuliah', 'dosen']),
        ], 201);
    }

    // ── PUT /api/admin/jadwals/{id} ──
    public function update(Request $request, $id)
    {
        $jadwal = Jadwal::findOrFail($id);

        $request->validate([
            'semester_id'    => 'required|exists:semesters,id',
            'kelas_id'       => 'required|exists:kelas,id',
            'mata_kuliah_id' => 'required|exists:mata_kuliahs,id',
            'dosen_id'       => 'required|exists:dosens,id',
            'hari'           => 'required|in:senin,selasa,rabu,kamis,jumat',
            'jam_mulai'      => 'required|date_format:H:i',
            'jam_selesai'    => 'required|date_format:H:i|after:jam_mulai',
            'ruangan'        => 'nullable|string|max:100',
        ]);

        $request->merge([
            'jam_mulai'  => \Carbon\Carbon::createFromFormat('H:i', substr($request->jam_mulai, 0, 5))->format('H:i'),
            'jam_selesai' => \Carbon\Carbon::createFromFormat('H:i', substr($request->jam_selesai, 0, 5))->format('H:i'),
        ]);

        $bentrok = $this->cekBentrok($request, $id); // exclude diri sendiri
        if ($bentrok) {
            return response()->json(['message' => $bentrok], 422);
        }

        $jadwal->update($request->only([
            'semester_id',
            'kelas_id',
            'mata_kuliah_id',
            'dosen_id',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'ruangan'
        ]));

        return response()->json([
            'message' => 'Jadwal berhasil diupdate',
            'data'    => $jadwal->load(['semester', 'kelas', 'mataKuliah', 'dosen']),
        ]);
    }

    // ── DELETE /api/admin/jadwals/{id} ──
    public function destroy($id)
    {
        Jadwal::findOrFail($id)->delete();
        return response()->json(['message' => 'Jadwal berhasil dihapus']);
    }

    // ── Helper: Cek Bentrok ──
    private function cekBentrok(Request $request, $excludeId = null): ?string
    {
        $base = Jadwal::where('semester_id', $request->semester_id)
            ->where('hari', $request->hari)
            ->where('jam_mulai', '<', $request->jam_selesai)
            ->where('jam_selesai', '>', $request->jam_mulai)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        if ($base->clone()->where('kelas_id', $request->kelas_id)->exists()) {
            return 'Kelas sudah memiliki jadwal di hari dan jam tersebut!';
        }

        if ($base->clone()->where('dosen_id', $request->dosen_id)->exists()) {
            return 'Dosen sudah memiliki jadwal di hari dan jam tersebut!';
        }

        if ($request->ruangan && $base->clone()->where('ruangan', $request->ruangan)->exists()) {
            return 'Ruangan sudah dipakai di hari dan jam tersebut!';
        }

        return null;
    }
}
