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
}