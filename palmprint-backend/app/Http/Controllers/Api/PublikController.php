<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\ProgramStudi;
use App\Models\Kelas;

class PublikController extends Controller
{
    // GET /api/jurusans
    public function jurusans()
    {
        return response()->json(
            Jurusan::orderBy('nama')->get(['id', 'kode', 'nama'])
        );
    }

    // GET /api/jurusans/{id}/prodis
    public function prodisByJurusan($id)
    {
        $prodis = ProgramStudi::where('jurusan_id', $id)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);

        return response()->json($prodis);
    }

    // GET /api/prodis/{id}/kelas
    public function kelasByProdi($id)
    {
        $kelas = Kelas::where('prodi_id', $id)
            ->whereHas('semester', fn($q) => $q->where('is_active', true))
            ->with('semester:id,nama')
            ->orderBy('nama')
            ->get(['id', 'prodi_id', 'semester_id', 'nama']);

        return response()->json($kelas);
    }
}