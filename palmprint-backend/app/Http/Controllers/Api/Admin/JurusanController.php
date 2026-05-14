<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use Illuminate\Http\Request;

class JurusanController extends Controller
{
    // GET /api/admin/jurusans
    public function index()
    {
        return response()->json(
            Jurusan::with('programStudis')->orderBy('nama')->get()
        );
    }

    // POST /api/admin/jurusans
    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:10|unique:jurusans,kode',
            'nama' => 'required|string|max:100',
        ]);

        $jurusan = Jurusan::create($request->only(['kode', 'nama']));

        return response()->json([
            'message' => 'Jurusan berhasil ditambahkan',
            'data'    => $jurusan->load('programStudis'),
        ], 201);
    }

    // PUT /api/admin/jurusans/{id}
    public function update(Request $request, $id)
    {
        $jurusan = Jurusan::findOrFail($id);

        $request->validate([
            'kode' => 'required|string|max:10|unique:jurusans,kode,' . $id,
            'nama' => 'required|string|max:100',
        ]);

        $jurusan->update($request->only(['kode', 'nama']));

        return response()->json([
            'message' => 'Jurusan berhasil diupdate',
            'data'    => $jurusan->load('programStudis'),
        ]);
    }

    // DELETE /api/admin/jurusans/{id}
    public function destroy($id)
    {
        $jurusan = Jurusan::findOrFail($id);

        // Cek apakah jurusan masih punya prodi
        if ($jurusan->programStudis()->exists()) {
            return response()->json([
                'message' => 'Jurusan tidak bisa dihapus karena masih memiliki Program Studi!'
            ], 422);
        }

        $jurusan->delete();
        return response()->json(['message' => 'Jurusan berhasil dihapus']);
    }
}