<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use Illuminate\Http\Request;

class ProgramStudiController extends Controller
{
    // GET /api/admin/prodis?jurusan_id=
    public function index(Request $request)
    {
        $query = ProgramStudi::with('jurusan')->orderBy('nama');

        if ($request->jurusan_id) {
            $query->where('jurusan_id', $request->jurusan_id);
        }

        return response()->json($query->get());
    }

    // POST /api/admin/prodis
    public function store(Request $request)
    {
        $request->validate([
            'jurusan_id' => 'required|exists:jurusans,id',
            'kode'       => 'required|string|max:10|unique:program_studis,kode',
            'nama'       => 'required|string|max:100',
        ]);

        $prodi = ProgramStudi::create($request->only([
            'jurusan_id', 'kode', 'nama'
        ]));

        return response()->json([
            'message' => 'Program Studi berhasil ditambahkan',
            'data'    => $prodi->load('jurusan'),
        ], 201);
    }

    // PUT /api/admin/prodis/{id}
    public function update(Request $request, $id)
    {
        $prodi = ProgramStudi::findOrFail($id);

        $request->validate([
            'jurusan_id' => 'required|exists:jurusans,id',
            'kode'       => 'required|string|max:10|unique:program_studis,kode,' . $id,
            'nama'       => 'required|string|max:100',
        ]);

        $prodi->update($request->only(['jurusan_id', 'kode', 'nama']));

        return response()->json([
            'message' => 'Program Studi berhasil diupdate',
            'data'    => $prodi->load('jurusan'),
        ]);
    }

    // DELETE /api/admin/prodis/{id}
    public function destroy($id)
    {
        $prodi = ProgramStudi::findOrFail($id);

        // Cek apakah prodi masih punya kelas atau matkul
        if ($prodi->kelas()->exists()) {
            return response()->json([
                'message' => 'Prodi tidak bisa dihapus karena masih memiliki Kelas!'
            ], 422);
        }

        if ($prodi->mataKuliahs()->exists()) {
            return response()->json([
                'message' => 'Prodi tidak bisa dihapus karena masih memiliki Mata Kuliah!'
            ], 422);
        }

        $prodi->delete();
        return response()->json(['message' => 'Program Studi berhasil dihapus']);
    }
}