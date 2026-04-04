<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index()
    {
        return response()->json(
            Kelas::with('semester')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'        => 'required|string',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $kelas = Kelas::create($request->only(['nama', 'semester_id']));

        return response()->json([
            'message' => 'Kelas berhasil dibuat',
            'data'    => $kelas->load('semester'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->update($request->only(['nama', 'semester_id']));

        return response()->json([
            'message' => 'Kelas berhasil diupdate',
            'data'    => $kelas->load('semester'),
        ]);
    }

    public function destroy($id)
    {
        Kelas::findOrFail($id)->delete();
        return response()->json(['message' => 'Kelas berhasil dihapus']);
    }

    // Tambah mahasiswa ke kelas
    public function tambahMahasiswa(Request $request, $id)
    {
        $request->validate([
            'mahasiswa_id' => 'required|exists:mahasiswas,id',
        ]);

        $kelas = Kelas::findOrFail($id);

        // Cek sudah ada atau belum
        $sudahAda = $kelas->mahasiswas()->where('mahasiswa_id', $request->mahasiswa_id)->exists();
        if ($sudahAda) {
            return response()->json(['message' => 'Mahasiswa sudah ada di kelas ini'], 422);
        }

        $kelas->mahasiswas()->attach($request->mahasiswa_id);

        return response()->json(['message' => 'Mahasiswa berhasil ditambahkan ke kelas']);
    }

    // Lihat mahasiswa di kelas
    public function mahasiswas($id)
    {
        $kelas = Kelas::with('mahasiswas')->findOrFail($id);
        return response()->json($kelas->mahasiswas);
    }
}