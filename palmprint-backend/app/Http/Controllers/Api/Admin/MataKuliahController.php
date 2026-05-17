<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataKuliah;
use Illuminate\Http\Request;

class MataKuliahController extends Controller
{
    // GET /api/admin/matkuls?semester_id=&prodi_id=
    public function index(Request $request)
    {
        $query = MataKuliah::with(['semester', 'prodi.jurusan'])->latest();

        if ($request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->prodi_id) {
            $query->where('prodi_id', $request->prodi_id);
        }

        return response()->json($query->get());
    }

    // POST /api/admin/matkuls
    public function store(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'prodi_id'    => 'required|exists:program_studis,id',
            'kode'        => 'required|string|max:20|unique:mata_kuliahs,kode',
            'nama'        => 'required|string|max:100',
            'sks'         => 'required|integer|min:1|max:6',
        ]);

        $matkul = MataKuliah::create($request->only([
            'semester_id', 'prodi_id', 'kode', 'nama', 'sks'
        ]));

        return response()->json([
            'message' => 'Mata kuliah berhasil ditambahkan',
            'data'    => $matkul->load(['semester', 'prodi.jurusan']),
        ], 201);
    }

    // PUT /api/admin/matkuls/{id}
    public function update(Request $request, $id)
    {
        $matkul = MataKuliah::findOrFail($id);

        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'prodi_id'    => 'required|exists:program_studis,id',
            'kode'        => 'required|string|max:20|unique:mata_kuliahs,kode,' . $id,
            'nama'        => 'required|string|max:100',
            'sks'         => 'required|integer|min:1|max:6',
        ]);

        $matkul->update($request->only([
            'semester_id', 'prodi_id', 'kode', 'nama', 'sks'
        ]));

        return response()->json([
            'message' => 'Mata kuliah berhasil diupdate',
            'data'    => $matkul->load(['semester', 'prodi.jurusan']),
        ]);
    }

    // DELETE /api/admin/matkuls/{id}
    public function destroy($id)
    {
        $matkul = MataKuliah::findOrFail($id);

        if ($matkul->jadwals()->exists()) {
            return response()->json([
                'message' => 'Mata kuliah tidak bisa dihapus karena sudah dipakai di jadwal!'
            ], 422);
        }

        $matkul->delete();
        return response()->json(['message' => 'Mata kuliah berhasil dihapus']);
    }
}