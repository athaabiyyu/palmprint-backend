<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PythonHelper;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\PalmprintTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

        // Simpan semua foto sementara
        $fotoPaths = [];
        foreach (['foto_1', 'foto_2', 'foto_3'] as $fotoKey) {
            $fileName    = uniqid() . '.' . $request->file($fotoKey)->getClientOriginalExtension();
            $fullPath    = storage_path('app/temp/' . $fileName);
            $request->file($fotoKey)->move(storage_path('app/temp'), $fileName);
            $fotoPaths[] = $fullPath;
        }

        // Panggil FastAPI: 3 foto sekaligus -> 21 vector (1 asli + 6 augmented per foto)
        $result = PythonHelper::extractFeaturesForRegistration($fotoPaths);

        // Hapus semua foto sementara
        foreach ($fotoPaths as $path) {
            if (file_exists($path)) unlink($path);
        }

        // Validasi hasil
        if ($result['status'] !== 'success') {
            return response()->json([
                'message' => $result['message'] ?? 'Gagal ekstraksi fitur palmprint',
                'type'    => $result['type'] ?? 'unknown',
            ], 422);
        }

        $vectors = $result['vectors'];

        if (count($vectors) === 0) {
            Log::error('[AuthController] Registrasi gagal: 0 vector dihasilkan');
            return response()->json([
                'message' => 'Gagal memproses template palmprint (tidak ada vector dihasilkan)',
            ], 422);
        }

        // Simpan mahasiswa
        $mahasiswa = Mahasiswa::create([
            'nim'      => $request->nim,
            'nama'     => $request->nama,
            'password' => Hash::make($request->password),
        ]);

        // Simpan SEMUA vector (21x) dengan model_version aktif
        $modelVersion = config('palmprint.model_version');
        Log::info('[AuthController] Registrasi ' . $mahasiswa->nim . ' - model_version: ' . $modelVersion . ', total_vectors: ' . count($vectors));

        foreach ($vectors as $i => $vector) {
            PalmprintTemplate::create([
                'mahasiswa_id'   => $mahasiswa->id,
                'feature_vector' => json_encode($vector),
                'sample_index'   => $i + 1, // 1..21
                'model_version'  => $modelVersion,
            ]);
        }

        $token = $mahasiswa->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'           => 'Registrasi berhasil',
            'token'             => $token,
            'sudah_pilih_kelas' => false,
            'mahasiswa'         => $mahasiswa,
        ], 201);
    }

    // ==================== RE-REGISTRASI PALMPRINT ====================
    // Dipanggil ketika user perlu update template setelah model retrain
    public function reRegisterPalmprint(Request $request)
    {
        $request->validate([
            'foto_1' => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
            'foto_2' => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
            'foto_3' => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
        ]);

        $mahasiswa    = $request->user();
        $modelVersion = config('palmprint.model_version');

        // Simpan foto sementara
        $fotoPaths = [];
        foreach (['foto_1', 'foto_2', 'foto_3'] as $fotoKey) {
            $fileName    = uniqid() . '.' . $request->file($fotoKey)->getClientOriginalExtension();
            $fullPath    = storage_path('app/temp/' . $fileName);
            $request->file($fotoKey)->move(storage_path('app/temp'), $fileName);
            $fotoPaths[] = $fullPath;
        }

        // Ekstraksi fitur: 3 foto -> 21 vector
        $result = PythonHelper::extractFeaturesForRegistration($fotoPaths);

        foreach ($fotoPaths as $path) {
            if (file_exists($path)) unlink($path);
        }

        if ($result['status'] !== 'success') {
            return response()->json([
                'message' => $result['message'] ?? 'Gagal ekstraksi fitur palmprint',
                'type'    => $result['type'] ?? 'unknown',
            ], 422);
        }

        $vectors = $result['vectors'];

        if (count($vectors) === 0) {
            Log::error('[AuthController] Re-registrasi gagal: 0 vector untuk mahasiswa_id ' . $mahasiswa->id);
            return response()->json([
                'message' => 'Gagal memproses template palmprint',
            ], 422);
        }

        // Hapus template lama (semua versi) - baru hapus SETELAH ekstraksi baru sukses,
        // supaya kalau FastAPI gagal, template lama user tidak ikut hilang.
        PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)->delete();

        Log::info('[AuthController] Re-registrasi ' . $mahasiswa->nim . ' - model_version: ' . $modelVersion . ', total_vectors: ' . count($vectors));

        foreach ($vectors as $i => $vector) {
            PalmprintTemplate::create([
                'mahasiswa_id'   => $mahasiswa->id,
                'feature_vector' => json_encode($vector),
                'sample_index'   => $i + 1,
                'model_version'  => $modelVersion,
            ]);
        }

        return response()->json([
            'message'        => 'Template palmprint berhasil diperbarui',
            'model_version'  => $modelVersion,
            'total_template' => count($vectors),
        ]);
    }

    // ==================== CEK STATUS TEMPLATE ====================
    // Endpoint untuk Flutter cek apakah template user masih valid
    public function cekStatusTemplate(Request $request)
    {
        $mahasiswa    = $request->user();
        $modelVersion = config('palmprint.model_version');

        $templateValid = PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)
            ->where('model_version', $modelVersion)
            ->exists();

        return response()->json([
            'template_valid'        => $templateValid,
            'model_version_aktif'   => $modelVersion,
            'perlu_re_registrasi'   => !$templateValid,
            'pesan'                 => $templateValid
                ? 'Template palmprint valid'
                : config('palmprint.outdated_template_message'),
        ]);
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

        $sudahPilihKelas = $mahasiswa->kelas()->exists();
        $modelVersion    = config('palmprint.model_version');

        // Cek status template sekalian saat login
        $templateValid = PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)
            ->where('model_version', $modelVersion)
            ->exists();

        $token = $mahasiswa->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'             => 'Login berhasil',
            'token'               => $token,
            'sudah_pilih_kelas'   => $sudahPilihKelas,
            'template_valid'      => $templateValid,
            'perlu_re_registrasi' => !$templateValid,
            'data'                => $mahasiswa,
        ]);
    }

    // ==================== PILIH KELAS ====================
    public function pilihKelas(Request $request)
    {
        $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
        ]);

        $mahasiswa = $request->user();

        if ($mahasiswa->kelas()->exists()) {
            return response()->json([
                'message' => 'Anda sudah memiliki kelas, tidak bisa diganti',
            ], 422);
        }

        $mahasiswa->kelas()->attach($request->kelas_id);

        return response()->json([
            'message' => 'Kelas berhasil dipilih',
            'kelas'   => Kelas::find($request->kelas_id),
        ]);
    }

    // ==================== PROFIL ====================
    public function profil(Request $request)
    {
        $mahasiswa    = $request->user()->load('kelas.jadwals.mataKuliah', 'kelas.jadwals.dosen');
        $modelVersion = config('palmprint.model_version');

        $templateValid = PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)
            ->where('model_version', $modelVersion)
            ->exists();

        return response()->json([
            'data'                => $mahasiswa,
            'sudah_pilih_kelas'   => $mahasiswa->kelas()->exists(),
            'template_valid'      => $templateValid,
            'perlu_re_registrasi' => !$templateValid,
        ]);
    }

    // ==================== DAFTAR KELAS ====================
    public function daftarKelas()
    {
        $kelas = Kelas::whereHas('semester', function ($q) {
            $q->where('is_active', true);
        })->with('semester')->get();

        return response()->json($kelas);
    }
}