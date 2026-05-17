<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MahasiswaController extends Controller
{
       // GET /api/admin/mahasiswas?kelas_id=&prodi_id=&palmprint=
       public function index(Request $request)
       {
              $query = Mahasiswa::with([
                     'kelas.prodi.jurusan',
                     'palmprintTemplates'
              ])->latest();

              // Filter per kelas
              if ($request->kelas_id) {
                     $query->whereHas(
                            'kelas',
                            fn($q) =>
                            $q->where('kelas.id', $request->kelas_id)
                     );
              }

              // Filter per prodi
              if ($request->prodi_id) {
                     $query->whereHas(
                            'kelas',
                            fn($q) =>
                            $q->where('prodi_id', $request->prodi_id)
                     );
              }

              // Filter per status palmprint
              if ($request->palmprint === 'sudah') {
                     $query->has('palmprintTemplates');
              } elseif ($request->palmprint === 'belum') {
                     $query->doesntHave('palmprintTemplates');
              }

              $mahasiswas = $query->get()->map(fn($m) => [
                     'id'              => $m->id,
                     'nim'             => $m->nim,
                     'nama'            => $m->nama,
                     'is_active'       => $m->is_active,
                     'created_at'      => $m->created_at->format('d/m/Y'),
                     'kelas'           => $m->kelas->first()?->nama ?? '-',
                     'kelas_id'        => $m->kelas->first()?->id,
                     'prodi'           => $m->kelas->first()?->prodi?->nama ?? '-',
                     'prodi_id'        => $m->kelas->first()?->prodi?->id,        // ← tambah
                     'jurusan'         => $m->kelas->first()?->prodi?->jurusan?->nama ?? '-',
                     'jurusan_id'      => $m->kelas->first()?->prodi?->jurusan?->id, // ← tambah
                     'palmprint_count' => $m->palmprintTemplates->count(),
                     'sudah_palmprint' => $m->palmprintTemplates->count() >= 3,
              ]);

              return response()->json($mahasiswas);
       }

       // PUT /api/admin/mahasiswas/{id}/toggle-aktif
       public function toggleAktif($id)
       {
              $mahasiswa = Mahasiswa::findOrFail($id);
              $mahasiswa->update(['is_active' => !$mahasiswa->is_active]);

              return response()->json([
                     'message'   => $mahasiswa->is_active ? 'Akun diaktifkan' : 'Akun dinonaktifkan',
                     'is_active' => $mahasiswa->is_active,
              ]);
       }

       // PUT /api/admin/mahasiswas/{id}/pindah-kelas
       public function pindahKelas(Request $request, $id)
       {
              $request->validate([
                     'kelas_id' => 'required|exists:kelas,id',
              ]);

              $mahasiswa = Mahasiswa::findOrFail($id);

              // Sync kelas (replace kelas lama)
              $mahasiswa->kelas()->sync([$request->kelas_id]);

              return response()->json([
                     'message' => 'Kelas mahasiswa berhasil dipindah',
                     'kelas'   => Kelas::find($request->kelas_id),
              ]);
       }

       // PUT /api/admin/mahasiswas/{id}/reset-password
       public function resetPassword(Request $request, $id)
       {
              $request->validate([
                     'password' => 'required|string|min:6',
              ]);

              $mahasiswa = Mahasiswa::findOrFail($id);
              $mahasiswa->update([
                     'password' => Hash::make($request->password),
              ]);

              return response()->json([
                     'message' => 'Password berhasil direset',
              ]);
       }
}
