<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PythonHelper;
use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\PalmprintTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // ==================== REGISTER ====================
    public function register(Request $request)
    {
        $request->validate([
            'nim'      => 'required|string|unique:mahasiswas,nim',
            'nama'     => 'required|string',
            'foto_1'   => 'required|image|max:5120',
            'foto_2'   => 'required|image|max:5120',
            'foto_3'   => 'required|image|max:5120',
        ]);

        $vectors = [];

        // Proses 3 foto
        foreach (['foto_1', 'foto_2', 'foto_3'] as $index => $fotoKey) {

    // Simpan foto dengan cara berbeda
    $fileName = uniqid() . '.' . $request->file($fotoKey)->getClientOriginalExtension();
    $fullPath = storage_path('app/temp/' . $fileName);
    
    // Move file langsung ke folder temp
    $request->file($fotoKey)->move(storage_path('app/temp'), $fileName);
    
    Log::info('Full path: ' . $fullPath);
    Log::info('File exists: ' . (file_exists($fullPath) ? 'YES' : 'NO'));

    // Panggil Python
    $result = PythonHelper::extractFeature($fullPath);

    // Hapus foto sementara
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    if (!$result) {
        return response()->json([
            'message' => "Gagal ekstraksi fitur pada foto ke-" . ($index + 1),
        ], 422);
    }

    $vectors[] = [
        'vector'       => $result['vector'],
        'threshold'    => $result['threshold'],
        'sample_index' => $index + 1,
    ];
}

        // Simpan mahasiswa
        $mahasiswa = Mahasiswa::create([
            'nim'  => $request->nim,
            'nama' => $request->nama,
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
            'message' => 'Registrasi berhasil',
            'token'   => $token,
            'data'    => $mahasiswa,
        ], 201);
    }

    // ==================== LOGIN ====================
    public function login(Request $request)
    {
        // $request->validate([
        //     'nim'  => 'required|string',
        //     'foto' => 'required|image|max:5120',
        // ]);
        $request->validate([
            'nim'  => 'required|string',
            'foto' => 'required|mimes:jpg,jpeg,png,bmp,tiff,tif|max:10240',
        ]);

        // Cari mahasiswa
        $mahasiswa = Mahasiswa::where('nim', $request->nim)->first();
        if (!$mahasiswa) {
            return response()->json(['message' => 'NIM tidak ditemukan'], 404);
        }

        // Simpan foto sementara
        $fileName = uniqid() . '.' . $request->file('foto')->getClientOriginalExtension();
        $fullPath = storage_path('app/temp/' . $fileName);
        $request->file('foto')->move(storage_path('app/temp'), $fileName);

        // Ekstraksi fitur foto login
        $result = PythonHelper::extractFeature($fullPath);

        // Hapus foto sementara
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        if (!$result) {
            return response()->json(['message' => 'Gagal ekstraksi fitur foto'], 422);
        }

        $queryVector = $result['vector'];
        $threshold   = $result['threshold'];

        // Ambil semua template mahasiswa
        $templates = PalmprintTemplate::where('mahasiswa_id', $mahasiswa->id)->get();

        $bestScore = 0;
        foreach ($templates as $template) {
            $storedVector = json_decode($template->feature_vector, true);
            $score        = PythonHelper::cosineSimilarity($queryVector, $storedVector);
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }

        if ($bestScore >= $threshold) {
            $token = $mahasiswa->createToken('auth_token')->plainTextToken;
            return response()->json([
                'message'    => 'Login berhasil',
                'token'      => $token,
                'similarity' => $bestScore,
                'data'       => $mahasiswa,
            ]);
        }

        return response()->json([
            'message'    => 'Telapak tangan tidak dikenali',
            'similarity' => $bestScore,
        ], 401);
    }
}