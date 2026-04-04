<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\PalmprintTemplate;
use App\Models\Kelas;
use App\Helpers\PythonHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ==================== REGISTER ====================
    public function register(Request $request)
    {
        $request->validate([
            'nim'      => 'required|string|unique:mahasiswas,nim',
            'nama'     => 'required|string',
            'password' => 'required|string|min:6',
            'foto_1'   => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
            'foto_2'   => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
            'foto_3'   => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
        ]);

        $vectors = [];

        foreach (['foto_1', 'foto_2', 'foto_3'] as $index => $fotoKey) {
            $fileName = uniqid() . '.' . $request->file($fotoKey)->getClientOriginalExtension();
            $fullPath = storage_path('app/temp/' . $fileName);
            $request->file($fotoKey)->move(storage_path('app/temp'), $fileName);

            $result = PythonHelper::extractFeature($fullPath);

            if (file_exists($fullPath)) unlink($fullPath);

            if (!$result) {
                return response()->json([
                    'message' => "Gagal ekstraksi fitur pada foto ke-" . ($index + 1),
                ], 422);
            }

            $vectors[] = [
                'vector'       => $result['vector'],
                'sample_index' => $index + 1,
            ];
        }

        // Simpan mahasiswa
        $mahasiswa = Mahasiswa::create([
            'nim'      => $request->nim,
            'nama'     => $request->nama,
            'password' => Hash::make($request->password),
        ]);

        // Simpan 3 template vektor
        foreach ($vectors as $item) {
            PalmprintTemplate::create([
                'mahasiswa_id'   => $mahasiswa->id,
                'feature_vector' => json_encode($item['vector']),
                'sample_index'   => $item['sample_index'],
            ]);
        }

        $token = $mahasiswa->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Registrasi berhasil',
            'token'        => $token,
            'sudah_pilih_kelas' => false,
            'data'         => $mahasiswa,
        ], 201);
    }

    // ==================== LOGIN ====================
    public function login(Request $request)
    {
        $request->validate([
            'nim'      => 'required|string',
            'password' => 'required|string',
        ]);

        $mahasiswa = Mahasiswa::where('nim', $request->nim)->first();

        if (!$mahasiswa || !Hash::check($request->password, $mahasiswa->password)) {
            return response()->json(['message' => 'NIM atau password salah'], 401);
        }

        // Cek apakah sudah punya kelas
        $sudahPilihKelas = $mahasiswa->kelas()->exists();

        $token = $mahasiswa->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'           => 'Login berhasil',
            'token'             => $token,
            'sudah_pilih_kelas' => $sudahPilihKelas,
            'data'              => $mahasiswa,
        ]);
    }

    // ==================== PILIH KELAS ====================
    public function pilihKelas(Request $request)
    {
        $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
        ]);

        $mahasiswa = $request->user();

        // Cek apakah sudah punya kelas
        if ($mahasiswa->kelas()->exists()) {
            return response()->json([
                'message' => 'Anda sudah memiliki kelas, tidak bisa diganti',
            ], 422);
        }

        // Assign kelas
        $mahasiswa->kelas()->attach($request->kelas_id);

        return response()->json([
            'message' => 'Kelas berhasil dipilih',
            'kelas'   => Kelas::find($request->kelas_id),
        ]);
    }

    // ==================== PROFIL ====================
    public function profil(Request $request)
    {
        $mahasiswa = $request->user()->load('kelas.jadwals.mataKuliah', 'kelas.jadwals.dosen');

        return response()->json([
            'data'              => $mahasiswa,
            'sudah_pilih_kelas' => $mahasiswa->kelas()->exists(),
        ]);
    }

    // ==================== DAFTAR KELAS ====================
    public function daftarKelas()
    {
        // Ambil kelas dari semester aktif
        $kelas = Kelas::whereHas('semester', function ($q) {
            $q->where('is_active', true);
        })->with('semester')->get();

        return response()->json($kelas);
    }
}