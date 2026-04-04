<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataKuliah;

class MatkulWebController extends Controller
{
    public function index()
    {
        $matkuls = MataKuliah::latest()->get();
        return view('admin.matkul.index', compact('matkuls'));
    }
}