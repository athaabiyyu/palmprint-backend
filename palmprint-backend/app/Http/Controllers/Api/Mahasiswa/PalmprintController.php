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

          $fileName = uniqid() . '.' . $request->file('foto')->getClientOriginalExtension();
          $fullPath = storage_path('app/temp/' . $fileName);
          $request->file('foto')->move(storage_path('app/temp'), $fileName);

          $results = PythonHelper::extractFeatures([$fullPath]);

          if (file_exists($fullPath)) unlink($fullPath);

          if (!$results || $results[0]['status'] !== 'success') {
               return response()->json([
                    'valid'   => false,
                    'message' => $results[0]['message'] ?? 'Foto tidak valid',
               ]);
          }

          return response()->json([
               'valid'   => true,
               'message' => 'Foto valid',
          ]);
     }
}
