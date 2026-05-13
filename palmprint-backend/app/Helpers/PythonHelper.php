<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class PythonHelper
{
       /**
        * Panggil palmprint_api.py dengan path gambar
        * Return: array vektor HOG-SGF atau null kalau gagal
        */

       
public static function extractFeatures(array $imagePaths): ?array
{
    $pythonPath = 'C:\\Python313\\python.exe';
    $scriptPath = 'D:\\xampp\\htdocs\\palmprint-backend\\palmprint-ml\\palmprint_api.py';

    // Gabung semua path dengan spasi
    $pathArgs = implode('" "', $imagePaths);
    $command  = "{$pythonPath} \"{$scriptPath}\" --images \"{$pathArgs}\" 2>&1";
    $output   = shell_exec($command);

    Log::info('Python extractFeatures command: ' . $command);
    Log::info('Python extractFeatures output: ' . $output);

    if (!$output) return null;

    // Ambil baris terakhir yang berisi JSON
    $lines  = array_filter(explode("\n", trim($output)));
    $last   = end($lines);
    $result = json_decode($last, true);

    if (!$result || !is_array($result)) return null;

    // Cek semua berhasil
    foreach ($result as $item) {
        if ($item['status'] !== 'success') return null;
    }

    return $result;
}

       /**
        * Hitung cosine similarity antara dua vektor
        */
       public static function cosineSimilarity(array $a, array $b): float
       {
              $dot   = 0;
              $normA = 0;
              $normB = 0;

              foreach ($a as $i => $val) {
              $dot   += $val * ($b[$i] ?? 0);
              $normA += $val * $val;
              $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
              }

              if ($normA == 0 || $normB == 0) return 0.0;

              return $dot / (sqrt($normA) * sqrt($normB));
       }
}