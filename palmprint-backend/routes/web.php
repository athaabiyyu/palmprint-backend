<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SemesterWebController;
use App\Http\Controllers\Admin\KelasWebController;
use App\Http\Controllers\Admin\DosenWebController;
use App\Http\Controllers\Admin\MatkulWebController;
use App\Http\Controllers\Admin\JadwalWebController;

Route::get('/', fn() => redirect('/admin/dashboard'));

Route::prefix('admin')->group(function () {
    Route::get ('dashboard',  [DashboardController::class,   'index']);
    Route::get ('semester',   [SemesterWebController::class, 'index']);
    Route::get ('kelas',      [KelasWebController::class,    'index']);
    Route::get ('dosen',      [DosenWebController::class,    'index']);
    Route::get ('matkul',     [MatkulWebController::class,   'index']);
    Route::get ('jadwal',     [JadwalWebController::class,   'index']);
});