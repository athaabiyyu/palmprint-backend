<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    // GET /api/admin/kelas?prodi_id=&semester_id=
    public function index(Request $request)
    {
        $query = Kelas::with(['prodi.jurusan', 'semester'])->orderBy('nama');

        if ($request->prodi_id) {
            $query->where('prodi_id', $request->prodi_id);
        }

        if ($request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }

        return response()->json($query->get());
    }

    // POST /api/admin/kelas
    public function store(Request $request)
    {
        $request->validate([
            'prodi_id'   => 'required|exists:program_studis,id',
            'semester_id' => 'required|exists:semesters,id',
            'suffix'     => 'required|string|max:10', // contoh: 4B
        ]);

        // Auto-generate nama dari kode prodi + suffix
        $prodi = \App\Models\ProgramStudi::findOrFail($request->prodi_id);
        $nama  = $prodi->kode . '-' . strtoupper($request->suffix);

        // Cek duplikat nama di semester yang sama
        $exists = Kelas::where('nama', $nama)
            ->where('semester_id', $request->semester_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Kelas {$nama} sudah ada di semester ini!"
            ], 422);
        }

        $kelas = Kelas::create([
            'prodi_id'   => $request->prodi_id,
            'semester_id' => $request->semester_id,
            'nama'       => $nama,
        ]);

        return response()->json([
            'message' => 'Kelas berhasil ditambahkan',
            'data'    => $kelas->load(['prodi.jurusan', 'semester']),
        ], 201);
    }

    // PUT /api/admin/kelas/{id}
    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'prodi_id'    => 'required|exists:program_studis,id',
            'semester_id' => 'required|exists:semesters,id',
            'suffix'      => 'required|string|max:10',
        ]);

        $prodi = \App\Models\ProgramStudi::findOrFail($request->prodi_id);
        $nama  = $prodi->kode . '-' . strtoupper($request->suffix);

        // Cek duplikat nama di semester yang sama (kecuali diri sendiri)
        $exists = Kelas::where('nama', $nama)
            ->where('semester_id', $request->semester_id)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Kelas {$nama} sudah ada di semester ini!"
            ], 422);
        }

        $kelas->update([
            'prodi_id'    => $request->prodi_id,
            'semester_id' => $request->semester_id,
            'nama'        => $nama,
        ]);

        return response()->json([
            'message' => 'Kelas berhasil diupdate',
            'data'    => $kelas->load(['prodi.jurusan', 'semester']),
        ]);
    }

    // DELETE /api/admin/kelas/{id}
    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->mahasiswas()->exists()) {
            return response()->json([
                'message' => 'Kelas tidak bisa dihapus karena masih memiliki mahasiswa!'
            ], 422);
        }

        $kelas->delete();
        return response()->json(['message' => 'Kelas berhasil dihapus']);
    }

    // GET /api/admin/kelas/{id}/mahasiswa
    public function mahasiswas($id)
    {
        $kelas = Kelas::with('mahasiswas')->findOrFail($id);
        return response()->json($kelas->mahasiswas);
    }

    // POST /api/admin/kelas/{id}/mahasiswa
    public function tambahMahasiswa(Request $request, $id)
    {
        $request->validate([
            'mahasiswa_id' => 'required|exists:users,id',
        ]);

        $kelas = Kelas::findOrFail($id);
        $kelas->mahasiswas()->syncWithoutDetaching([$request->mahasiswa_id]);

        return response()->json(['message' => 'Mahasiswa berhasil ditambahkan ke kelas']);
    }
}