<?php

namespace App\Http\Controllers\Api\Mahasiswa;

use App\Helpers\PythonHelper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PalmprintController extends Controller
{

     public function validatePalm(Request $request)
     {
          $request->validate([
               'foto' => 'required|mimes:jpg,jpeg,png|max:10240',
          ]);

          $mahasiswa = $request->user();

          $fileName = uniqid() . '.' . $request->file('foto')->getClientOriginalExtension();
          $fullPath = storage_path('app/temp/' . $fileName);
          $request->file('foto')->move(storage_path('app/temp'), $fileName);

          $result = PythonHelper::extractFeature($fullPath, $mahasiswa->nama ?? null);

          if (file_exists($fullPath)) unlink($fullPath);

          if (!$result || $result['status'] !== 'success') {
               return response()->json([
                    'valid'   => false,
                    'message' => $result['message'] ?? 'Foto tidak valid',
               ]);
          }

          return response()->json([
               'valid'   => true,
               'message' => 'Foto valid',
          ]);
     }
}
