<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;

class SemesterWebController extends Controller
{
    public function index()
    {
        $semesters = Semester::latest()->get();
        return view('admin.semester.index', compact('semesters'));
    }
}