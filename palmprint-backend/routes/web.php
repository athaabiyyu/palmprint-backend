<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SemesterWebController;
use App\Http\Controllers\Admin\KelasWebController;
use App\Http\Controllers\Admin\DosenWebController;
use App\Http\Controllers\Admin\MatkulWebController;
use App\Http\Controllers\Admin\JadwalWebController;
use App\Http\Controllers\Web\Dosen\DosenAuthController;
use App\Http\Controllers\Web\Dosen\DosenDashboardController;
use App\Http\Controllers\Admin\RekapController;

Route::get('/', fn() => redirect('/admin/dashboard'));

Route::prefix('admin')->group(function () {
    Route::get ('dashboard',  [DashboardController::class,   'index']);
    Route::get ('semester',   [SemesterWebController::class, 'index']);
    Route::get ('kelas',      [KelasWebController::class,    'index']);
    Route::get ('dosen',      [DosenWebController::class,    'index']);
    Route::get ('matkul',     [MatkulWebController::class,   'index']);
    Route::get ('jadwal',     [JadwalWebController::class,   'index']);
    Route::get('rekap', [RekapController::class, 'index']);
    Route::get('rekap/export-excel',  [RekapController::class, 'exportExcel'])->name('rekap.export-excel');
    Route::get('rekap/export-pdf',    [RekapController::class, 'exportPdf'])->name('rekap.export-pdf');
});

Route::prefix('dosen')->group(function () {
    Route::get ('login',     [DosenAuthController::class,      'index']);
    Route::get ('dashboard', [DosenDashboardController::class, 'index']);
});