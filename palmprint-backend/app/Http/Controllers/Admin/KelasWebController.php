<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Semester;

class KelasWebController extends Controller
{
    public function index()
    {
        $kelas     = Kelas::with('semester')->latest()->get();
        $semesters = Semester::where('is_active', true)->get();
        return view('admin.kelas.index', compact('kelas', 'semesters'));
    }
}