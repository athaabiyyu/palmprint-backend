<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Surat;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SuratController extends Controller
{
    // GET /api/admin/surats?status=
    public function index(Request $request)
    {
        $query = Surat::with([
            'mahasiswa',
            'sesiAbsensi.jadwal.mataKuliah',
            'sesiAbsensi.jadwal.kelas',
            'sesiAbsensi.jadwal.dosen',
        ])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->prodi_id) {
            $query->whereHas('sesiAbsensi.jadwal.kelas', fn($q) =>
                $q->where('prodi_id', $request->prodi_id)
            );
        }

        return response()->json($query->get());
    }

    // PUT /api/admin/surats/{id}/review
    public function review(Request $request, $id)
    {
        $request->validate([
            'aksi'          => 'required|in:setujui,tolak',
            'catatan_admin' => 'nullable|string|max:500',
        ]);

        $surat = Surat::with(['sesiAbsensi', 'mahasiswa'])->findOrFail($id);

        if ($surat->status !== 'pending') {
            return response()->json([
                'message' => 'Surat ini sudah direview sebelumnya.'
            ], 422);
        }

        if ($request->aksi === 'setujui') {
            // Update status surat
            $surat->update([
                'status'       => 'disetujui',
                'catatan_admin' => $request->catatan_admin,
                'reviewed_at'  => Carbon::now(),
            ]);

            // Update atau buat record absensi
            Absensi::updateOrCreate(
                [
                    'sesi_absensi_id' => $surat->sesi_absensi_id,
                    'mahasiswa_id'    => $surat->mahasiswa_id,
                ],
                [
                    'status'      => $surat->jenis, // izin atau sakit
                    'waktu_absen' => Carbon::now(),
                ]
            );

            return response()->json([
                'message' => 'Surat disetujui, status absensi mahasiswa diupdate.',
            ]);

        } else {
            // Tolak surat
            if (!$request->catatan_admin) {
                return response()->json([
                    'message' => 'Catatan penolakan wajib diisi!'
                ], 422);
            }

            $surat->update([
                'status'        => 'ditolak',
                'catatan_admin' => $request->catatan_admin,
                'reviewed_at'   => Carbon::now(),
            ]);

            return response()->json([
                'message' => 'Surat ditolak.',
            ]);
        }
    }
}