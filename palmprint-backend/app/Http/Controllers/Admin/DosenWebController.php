<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;

class DosenWebController extends Controller
{
    public function index()
    {
        $dosens = Dosen::latest()->get();
        return view('admin.dosen.index', compact('dosens'));
    }
}