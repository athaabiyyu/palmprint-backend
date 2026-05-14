<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->foreignId('prodi_id')
                ->nullable()
                ->after('id')
                ->constrained('program_studis')
                ->nullOnDelete();
        });

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->foreignId('prodi_id')
                ->nullable()
                ->after('semester_id')
                ->constrained('program_studis')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['prodi_id']);
            $table->dropColumn('prodi_id');
        });

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->dropForeign(['prodi_id']);
            $table->dropColumn('prodi_id');
        });
    }
};
