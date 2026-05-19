<?php

namespace App\Http\Controllers\Api\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Surat;
use App\Models\SesiAbsensi;
use App\Models\Absensi;
use App\Models\Semester;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SuratController extends Controller
{
       // GET /api/mahasiswa/surats — riwayat surat mahasiswa
       public function index(Request $request)
       {
              $surats = Surat::with(['sesiAbsensi.jadwal.mataKuliah'])
                     ->where('mahasiswa_id', $request->user()->id)
                     ->latest()
                     ->get();

              return response()->json($surats);
       }

       // POST /api/mahasiswa/surats — upload surat
       public function store(Request $request)
       {
              $request->validate([
                     'sesi_absensi_id' => 'required|exists:sesi_absensis,id',
                     'jenis'           => 'required|in:izin,sakit',
                     'link_drive'      => 'required|url',
                     'keterangan'      => 'nullable|string|max:500',
              ]);

              $mahasiswa = $request->user();
              $sesi      = SesiAbsensi::with('jadwal.semester')->findOrFail($request->sesi_absensi_id);

              // Cek apakah mahasiswa memang alpha di sesi ini
              $absensi = Absensi::where('sesi_absensi_id', $sesi->id)
                     ->where('mahasiswa_id', $mahasiswa->id)
                     ->first();

              if ($absensi && $absensi->status !== 'alpha') {
                     return response()->json([
                            'message' => 'Kamu sudah tercatat hadir di sesi ini, tidak perlu upload surat.'
                     ], 422);
              }

              

              // Cek batas akhir semester aktif
              $semester = $sesi->jadwal->semester;
              if (!$semester || !$semester->is_active) {
                     return response()->json([
                            'message' => 'Semester sudah tidak aktif, tidak bisa upload surat.'
                     ], 422);
              }

              // Cek duplikat surat
              $sudahAda = Surat::where('mahasiswa_id', $mahasiswa->id)
                     ->where('sesi_absensi_id', $sesi->id)
                     ->exists();

              if ($sudahAda) {
                     return response()->json([
                            'message' => 'Kamu sudah mengajukan surat untuk sesi ini.'
                     ], 422);
              }

              $surat = Surat::create([
                     'mahasiswa_id'    => $mahasiswa->id,
                     'sesi_absensi_id' => $sesi->id,
                     'jenis'           => $request->jenis,
                     'link_drive'      => $request->link_drive,
                     'keterangan'      => $request->keterangan,
                     'status'          => 'pending',
              ]);

              return response()->json([
                     'message' => 'Surat berhasil diajukan, menunggu review admin.',
                     'data'    => $surat->load('sesiAbsensi.jadwal.mataKuliah'),
              ], 201);
       }
}
