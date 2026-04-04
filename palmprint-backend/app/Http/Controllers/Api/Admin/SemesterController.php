<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index()
    {
        return response()->json(Semester::latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'         => 'required|string',
            'tahun_ajaran' => 'required|string',
            'tipe'         => 'required|in:ganjil,genap',
        ]);

        $semester = Semester::create([
            'nama'         => $request->nama,
            'tahun_ajaran' => $request->tahun_ajaran,
            'tipe'         => $request->tipe,
            'is_active'    => false,
        ]);

        return response()->json([
            'message' => 'Semester berhasil dibuat',
            'data'    => $semester,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $semester = Semester::findOrFail($id);
        $semester->update($request->only(['nama', 'tahun_ajaran', 'tipe']));

        return response()->json([
            'message' => 'Semester berhasil diupdate',
            'data'    => $semester,
        ]);
    }

    public function destroy($id)
    {
        Semester::findOrFail($id)->delete();
        return response()->json(['message' => 'Semester berhasil dihapus']);
    }

    public function setActive($id)
    {
        // Nonaktifkan semua semester dulu
        Semester::where('is_active', true)->update(['is_active' => false]);

        // Aktifkan semester yang dipilih
        $semester = Semester::findOrFail($id);
        $semester->update(['is_active' => true]);

        return response()->json([
            'message' => 'Semester aktif berhasil diset',
            'data'    => $semester,
        ]);
    }
}