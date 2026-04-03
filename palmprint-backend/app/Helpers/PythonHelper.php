<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class PythonHelper
{
       /**
        * Panggil palmprint_api.py dengan path gambar
        * Return: array vektor HOG-SGF atau null kalau gagal
        */

       
       public static function extractFeature(string $imagePath): ?array
{
    $pythonPath = 'C:\\Python313\\python.exe';
    $scriptPath = 'D:\\xampp\\htdocs\\palmprint-backend\\palmprint-ml\\palmprint_api.py';

    $command = "{$pythonPath} \"{$scriptPath}\" --image \"{$imagePath}\" 2>&1";
    $output  = shell_exec($command);

    // Tambahkan log untuk debug
    Log::info('Python command: ' . $command);
    Log::info('Python output: ' . $output);

    if (!$output) {
        return null;
    }

    $lines  = array_filter(explode("\n", trim($output)));
    $last   = end($lines);
    $result = json_decode($last, true);

    if (!$result || $result['status'] !== 'success') {
        return null;
    }

    return [
        'vector'    => $result['vector'],
        'threshold' => $result['threshold'],
    ];
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