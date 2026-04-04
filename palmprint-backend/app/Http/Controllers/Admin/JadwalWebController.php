<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataKuliah;
use App\Models\Dosen;

class JadwalWebController extends Controller
{
    public function index()
    {
        $jadwals  = Jadwal::with(['kelas', 'mataKuliah', 'dosen'])->latest()->get();
        $kelas    = Kelas::all();
        $matkuls  = MataKuliah::all();
        $dosens   = Dosen::all();
        return view('admin.jadwal.index', compact('jadwals', 'kelas', 'matkuls', 'dosens'));
    }
}