<?php
namespace App\Http\Controllers\Web\Dosen;

use App\Http\Controllers\Controller;

class DosenDashboardController extends Controller
{
    public function index()
    {
        return view('dosen.dashboard');
    }
}