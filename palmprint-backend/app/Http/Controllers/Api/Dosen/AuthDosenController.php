<?php
namespace App\Http\Controllers\Api\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthDosenController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nip'      => 'required|string',
            'password' => 'required|string',
        ]);

        $dosen = Dosen::where('nip', $request->nip)->first();

        if (!$dosen || !Hash::check($request->password, $dosen->password)) {
            return response()->json(['message' => 'NIP atau password salah'], 401);
        }

        if (!$dosen->is_active) {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi admin.'
            ], 403);
        }

        $token = $dosen->createToken('dosen_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'data'    => $dosen,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }

    // ── GET profil dosen ──
    public function profil(Request $request)
    {
        return response()->json($request->user());
    }

    // ── PUT update profil ──
    public function updateProfil(Request $request)
    {
        $dosen = $request->user();

        $request->validate([
            'nama' => 'required|string|max:100',
            'nip'  => 'required|string|unique:dosens,nip,' . $dosen->id,
        ]);

        $dosen->update($request->only(['nama', 'nip']));

        // Update localStorage data via response
        return response()->json([
            'message' => 'Profil berhasil diupdate',
            'data'    => $dosen->fresh(),
        ]);
    }

    // ── PUT ganti password ──
    public function gantiPassword(Request $request)
    {
        $dosen = $request->user();

        $request->validate([
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:6',
        ]);

        // Cek password lama
        if (!Hash::check($request->password_lama, $dosen->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai!'
            ], 422);
        }

        $dosen->update([
            'password' => Hash::make($request->password_baru),
        ]);

        return response()->json([
            'message' => 'Password berhasil diubah',
        ]);
    }
}