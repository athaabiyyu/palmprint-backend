<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class MahasiswaWebController extends Controller
{
    public function index()
    {
        return view('admin.mahasiswa.index');
    }
}