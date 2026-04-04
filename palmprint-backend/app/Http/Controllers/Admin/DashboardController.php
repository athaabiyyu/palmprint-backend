<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\MataKuliah;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'totalMahasiswa' => Mahasiswa::count(),
            'totalDosen'     => \App\Models\Dosen::count(),
            'totalKelas'     => Kelas::count(),
            'totalMatkul'    => MataKuliah::count(),
        ]);
    }
}