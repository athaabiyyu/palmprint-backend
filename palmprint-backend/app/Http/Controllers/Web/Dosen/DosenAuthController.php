<?php
namespace App\Http\Controllers\Web\Dosen;

use App\Http\Controllers\Controller;

class DosenAuthController extends Controller
{
    public function index()
    {
        return view('dosen.login');
    }
}