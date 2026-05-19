<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SuratWebController extends Controller
{
       public function index()
       {
              return view('admin.surat.index');
       }
}
