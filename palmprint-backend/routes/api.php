<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\SemesterController;
use App\Http\Controllers\Api\Admin\KelasController;
use App\Http\Controllers\Api\Admin\DosenController;
use App\Http\Controllers\Api\Admin\MataKuliahController;
use App\Http\Controllers\Api\Admin\JadwalController;
use Illuminate\Support\Facades\Route;

// ==================== AUTH MAHASISWA ====================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ==================== ADMIN ====================
Route::prefix('admin')->group(function () {

    // Semester
    Route::get   ('semesters',              [SemesterController::class, 'index']);
    Route::post  ('semesters',              [SemesterController::class, 'store']);
    Route::put   ('semesters/{id}',         [SemesterController::class, 'update']);
    Route::delete('semesters/{id}',         [SemesterController::class, 'destroy']);
    Route::post  ('semesters/{id}/aktif',   [SemesterController::class, 'setActive']);

    // Kelas
    Route::get   ('kelas',                          [KelasController::class, 'index']);
    Route::post  ('kelas',                          [KelasController::class, 'store']);
    Route::put   ('kelas/{id}',                     [KelasController::class, 'update']);
    Route::delete('kelas/{id}',                     [KelasController::class, 'destroy']);
    Route::post  ('kelas/{id}/mahasiswa',           [KelasController::class, 'tambahMahasiswa']);
    Route::get   ('kelas/{id}/mahasiswa',           [KelasController::class, 'mahasiswas']);

    // Dosen
    Route::get   ('dosens',        [DosenController::class, 'index']);
    Route::post  ('dosens',        [DosenController::class, 'store']);
    Route::put   ('dosens/{id}',   [DosenController::class, 'update']);
    Route::delete('dosens/{id}',   [DosenController::class, 'destroy']);

    // Mata Kuliah
    Route::get   ('matkuls',        [MataKuliahController::class, 'index']);
    Route::post  ('matkuls',        [MataKuliahController::class, 'store']);
    Route::put   ('matkuls/{id}',   [MataKuliahController::class, 'update']);
    Route::delete('matkuls/{id}',   [MataKuliahController::class, 'destroy']);

    // Jadwal
    Route::get   ('jadwals',                    [JadwalController::class, 'index']);
    Route::post  ('jadwals',                    [JadwalController::class, 'store']);
    Route::put   ('jadwals/{id}',               [JadwalController::class, 'update']);
    Route::delete('jadwals/{id}',               [JadwalController::class, 'destroy']);
    Route::get   ('jadwals/kelas/{kelasId}',    [JadwalController::class, 'byKelas']);
});