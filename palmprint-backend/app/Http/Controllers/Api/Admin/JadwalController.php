<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function index()
    {
        return response()->json(
            Jadwal::with(['kelas', 'mataKuliah', 'dosen'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'kelas_id'       => 'required|exists:kelas,id',
            'mata_kuliah_id' => 'required|exists:mata_kuliahs,id',
            'dosen_id'       => 'required|exists:dosens,id',
            'hari'           => 'required|in:senin,selasa,rabu,kamis,jumat',
            'jam_mulai'      => 'required|date_format:H:i',
            'jam_selesai'    => 'required|date_format:H:i|after:jam_mulai',
            'ruangan'        => 'nullable|string',
        ]);

        $jadwal = Jadwal::create($request->only([
            'kelas_id', 'mata_kuliah_id', 'dosen_id',
            'hari', 'jam_mulai', 'jam_selesai', 'ruangan'
        ]));

        return response()->json([
            'message' => 'Jadwal berhasil dibuat',
            'data'    => $jadwal->load(['kelas', 'mataKuliah', 'dosen']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jadwal = Jadwal::findOrFail($id);
        $jadwal->update($request->only([
            'kelas_id', 'mata_kuliah_id', 'dosen_id',
            'hari', 'jam_mulai', 'jam_selesai', 'ruangan'
        ]));

        return response()->json([
            'message' => 'Jadwal berhasil diupdate',
            'data'    => $jadwal->load(['kelas', 'mataKuliah', 'dosen']),
        ]);
    }

    public function destroy($id)
    {
        Jadwal::findOrFail($id)->delete();
        return response()->json(['message' => 'Jadwal berhasil dihapus']);
    }

    // Jadwal per kelas
    public function byKelas($kelasId)
    {
        $jadwals = Jadwal::with(['mataKuliah', 'dosen'])
            ->where('kelas_id', $kelasId)
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json($jadwals);
    }
}