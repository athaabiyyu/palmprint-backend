<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DosenController extends Controller
{
    // GET /api/admin/dosens
    public function index()
    {
        return response()->json(Dosen::latest()->get());
    }

    // POST /api/admin/dosens
    public function store(Request $request)
    {
        $request->validate([
            'nip'  => 'required|string|unique:dosens,nip',
            'nama' => 'required|string',
        ]);

        // Password default = NIP
        $dosen = Dosen::create([
            'nip'      => $request->nip,
            'nama'     => $request->nama,
            'password' => Hash::make($request->nip), // ← auto dari NIP
            'is_active' => true,
        ]);

        return response()->json([
            'message'          => 'Dosen berhasil ditambahkan',
            'info_password'    => 'Password default adalah NIP dosen',
            'data'             => $dosen,
        ], 201);
    }

    // PUT /api/admin/dosens/{id}
    public function update(Request $request, $id)
    {
        $dosen = Dosen::findOrFail($id);

        $request->validate([
            'nip'  => 'required|string|unique:dosens,nip,' . $id,
            'nama' => 'required|string',
        ]);

        $dosen->update($request->only(['nip', 'nama']));

        return response()->json([
            'message' => 'Dosen berhasil diupdate',
            'data'    => $dosen,
        ]);
    }

    // DELETE /api/admin/dosens/{id}
    public function destroy($id)
    {
        $dosen = Dosen::findOrFail($id);

        // Cek apakah dosen masih punya jadwal
        if ($dosen->jadwals()->exists()) {
            return response()->json([
                'message' => 'Dosen tidak bisa dihapus karena masih memiliki jadwal!'
            ], 422);
        }

        $dosen->delete();
        return response()->json(['message' => 'Dosen berhasil dihapus']);
    }

    // PUT /api/admin/dosens/{id}/toggle-aktif
    public function toggleAktif($id)
    {
        $dosen = Dosen::findOrFail($id);
        $dosen->update(['is_active' => !$dosen->is_active]);

        return response()->json([
            'message'   => $dosen->is_active ? 'Akun dosen diaktifkan' : 'Akun dosen dinonaktifkan',
            'is_active' => $dosen->is_active,
        ]);
    }

    // PUT /api/admin/dosens/{id}/reset-password
    public function resetPassword($id)
    {
        $dosen = Dosen::findOrFail($id);

        // Reset password ke NIP
        $dosen->update([
            'password' => Hash::make($dosen->nip),
        ]);

        return response()->json([
            'message' => 'Password berhasil direset ke NIP',
        ]);
    }
}