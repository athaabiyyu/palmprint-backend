<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataKuliah;
use Illuminate\Http\Request;

class MataKuliahController extends Controller
{
    public function index()
    {
        return response()->json(MataKuliah::latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|unique:mata_kuliahs,kode',
            'nama' => 'required|string',
            'sks'  => 'required|integer|min:1|max:6',
        ]);

        $matkul = MataKuliah::create($request->only(['kode', 'nama', 'sks']));

        return response()->json([
            'message' => 'Mata kuliah berhasil dibuat',
            'data'    => $matkul,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $matkul = MataKuliah::findOrFail($id);
        $matkul->update($request->only(['kode', 'nama', 'sks']));

        return response()->json([
            'message' => 'Mata kuliah berhasil diupdate',
            'data'    => $matkul,
        ]);
    }

    public function destroy($id)
    {
        MataKuliah::findOrFail($id)->delete();
        return response()->json(['message' => 'Mata kuliah berhasil dihapus']);
    }
}