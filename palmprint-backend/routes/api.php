<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\SemesterController;
use App\Http\Controllers\Api\Admin\KelasController;
use App\Http\Controllers\Api\Admin\DosenController;
use App\Http\Controllers\Api\Admin\MataKuliahController;
use App\Http\Controllers\Api\Admin\JadwalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Dosen\AuthDosenController;
use App\Http\Controllers\Api\Dosen\SesiAbsensiController;
use App\Http\Controllers\Api\Mahasiswa\JadwalMahasiswaController;
use App\Http\Controllers\Api\Mahasiswa\AbsensiController;
use App\Http\Controllers\Api\Admin\RekapApiController;
use App\Http\Controllers\Api\Admin\JurusanController;
use App\Http\Controllers\Api\Admin\ProgramStudiController;

// ==================== AUTH MAHASISWA ====================
Route::post('/register',      [AuthController::class, 'register']);
Route::post('/login',         [AuthController::class, 'login']);
Route::get('/daftar-kelas',  [AuthController::class, 'daftarKelas']);
// Protected — butuh token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/pilih-kelas', [AuthController::class, 'pilihKelas']);
    Route::get('/profil',      [AuthController::class, 'profil']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/pilih-kelas', [AuthController::class,          'pilihKelas']);
    Route::get('/profil',      [AuthController::class,          'profil']);

    // Mahasiswa
    Route::prefix('mahasiswa')->group(function () {
        Route::get('jadwal-hari-ini', [JadwalMahasiswaController::class, 'jadwalHariIni']);
        Route::post('absensi',         [AbsensiController::class,         'absensi']);
    });
});

// ==================== ADMIN ====================
Route::prefix('admin')->group(function () {

    // Jurusan
    Route::get('jurusans',      [JurusanController::class, 'index']);
    Route::post('jurusans',      [JurusanController::class, 'store']);
    Route::put('jurusans/{id}', [JurusanController::class, 'update']);
    Route::delete('jurusans/{id}', [JurusanController::class, 'destroy']);

    // Program Studi
    Route::get('prodis',        [ProgramStudiController::class, 'index']);
    Route::post('prodis',        [ProgramStudiController::class, 'store']);
    Route::put('prodis/{id}',   [ProgramStudiController::class, 'update']);
    Route::delete('prodis/{id}',   [ProgramStudiController::class, 'destroy']);

    // Semester
    Route::get('semesters',              [SemesterController::class, 'index']);
    Route::post('semesters',              [SemesterController::class, 'store']);
    Route::put('semesters/{id}',         [SemesterController::class, 'update']);
    Route::delete('semesters/{id}',         [SemesterController::class, 'destroy']);
    Route::post('semesters/{id}/aktif',   [SemesterController::class, 'setActive']);

    // Kelas
    Route::get('kelas',                          [KelasController::class, 'index']);
    Route::post('kelas',                          [KelasController::class, 'store']);
    Route::put('kelas/{id}',                     [KelasController::class, 'update']);
    Route::delete('kelas/{id}',                     [KelasController::class, 'destroy']);
    Route::post('kelas/{id}/mahasiswa',           [KelasController::class, 'tambahMahasiswa']);
    Route::get('kelas/{id}/mahasiswa',           [KelasController::class, 'mahasiswas']);

    // Dosen
    Route::get('dosens',        [DosenController::class, 'index']);
    Route::post('dosens',        [DosenController::class, 'store']);
    Route::put('dosens/{id}',   [DosenController::class, 'update']);
    Route::delete('dosens/{id}',   [DosenController::class, 'destroy']);

    // Mata Kuliah
    Route::get('matkuls',        [MataKuliahController::class, 'index']);
    Route::post('matkuls',        [MataKuliahController::class, 'store']);
    Route::put('matkuls/{id}',   [MataKuliahController::class, 'update']);
    Route::delete('matkuls/{id}',   [MataKuliahController::class, 'destroy']);

    // Jadwal
    Route::get('jadwals',                    [JadwalController::class, 'index']);
    Route::post('jadwals',                    [JadwalController::class, 'store']);
    Route::put('jadwals/{id}',               [JadwalController::class, 'update']);
    Route::delete('jadwals/{id}',               [JadwalController::class, 'destroy']);
    Route::get('jadwals/kelas/{kelasId}',    [JadwalController::class, 'byKelas']);

    // Rekap Absensi
    Route::get('rekap/jadwal/{kelasId}', [RekapApiController::class, 'jadwalByKelas']);
    Route::get('rekap',                  [RekapApiController::class, 'rekap']);
});

// ==================== DOSEN ====================
Route::post('dosen/login', [AuthDosenController::class, 'login']);

Route::middleware('auth:sanctum')->prefix('dosen')->group(function () {
    Route::post('logout',              [AuthDosenController::class,  'logout']);
    Route::get('jadwal-hari-ini',     [SesiAbsensiController::class, 'jadwalHariIni']);
    Route::post('sesi/buka',           [SesiAbsensiController::class, 'buka']);
    Route::post('sesi/{id}/tutup',     [SesiAbsensiController::class, 'tutup']);
    Route::get('sesi/{id}/detail',    [SesiAbsensiController::class, 'detail']);
    Route::get('sesi/aktif',          [SesiAbsensiController::class, 'sesiAktif']);
});
