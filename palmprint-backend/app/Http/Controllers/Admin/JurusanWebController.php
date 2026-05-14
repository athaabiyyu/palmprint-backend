<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class JurusanWebController extends Controller
{
    public function index()
    {
        return view('admin.jurusan.index');
    }
}