<?php

return [

    // Versi model aktif saat ini
    // Update ini setiap kali palmprint_modeling.ipynb di-retrain
    'model_version' => env('PALMPRINT_MODEL_VERSION', '2.0'),

    // Threshold default (dari threshold.pkl)
    // Ini hanya fallback — nilai sebenarnya dari Python
    'default_threshold' => 0.42,
    'threshold_override'       => 0.38, 

    // Pesan error untuk user ketika template tidak valid
    'outdated_template_message' => 'Template palmprint kamu sudah tidak valid karena sistem diperbarui. Silakan registrasi ulang palmprint di menu Profil.',

];