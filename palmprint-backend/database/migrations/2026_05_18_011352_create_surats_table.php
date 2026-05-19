<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')
                  ->constrained('mahasiswas')
                  ->cascadeOnDelete();
            $table->foreignId('sesi_absensi_id')
                  ->constrained('sesi_absensis')
                  ->cascadeOnDelete();
            $table->enum('jenis', ['izin', 'sakit']);
            $table->string('link_drive');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->text('catatan_admin')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Satu mahasiswa hanya bisa upload 1 surat per sesi
            $table->unique(['mahasiswa_id', 'sesi_absensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surats');
    }
};