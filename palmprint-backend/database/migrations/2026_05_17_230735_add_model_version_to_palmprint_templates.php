<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('palmprint_templates', function (Blueprint $table) {
            // Versi model saat template dibuat
            // Default '1.0' untuk template lama yang sudah ada
            $table->string('model_version', 10)->default('3.1.7')->after('sample_index');
        });

        // Set semua template lama ke versi '1.0' (sudah outdated)
        // Mereka perlu re-registrasi dengan model baru
        DB::table('palmprint_templates')->update(['model_version' => '3.1.7']);
    }

    public function down(): void
    {
        Schema::table('palmprint_templates', function (Blueprint $table) {
            $table->dropColumn('model_version');
        });
    }
};