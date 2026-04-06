<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\SesiAbsensi;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RekapController extends Controller
{
    public function index()
    {
        $kelas = Kelas::with('semester')->get();
        return view('admin.rekap.index', compact('kelas'));
    }

    // ── Helper ambil data rekap ──
    private function getRekapData(Request $request)
    {
        $jadwal = Jadwal::with(['mataKuliah', 'kelas.mahasiswas'])->findOrFail($request->jadwal_id);

        $query = SesiAbsensi::where('jadwal_id', $request->jadwal_id);
        if ($request->tanggal_dari)   $query->whereDate('tanggal', '>=', $request->tanggal_dari);
        if ($request->tanggal_sampai) $query->whereDate('tanggal', '<=', $request->tanggal_sampai);
        $sesis = $query->orderBy('tanggal')->get();

        $mahasiswas = $jadwal->kelas->mahasiswas;

        $rekap = $mahasiswas->map(function ($m) use ($sesis) {
            $detail = $sesis->map(function ($sesi) use ($m) {
                $absensi = Absensi::where('sesi_absensi_id', $sesi->id)
                    ->where('mahasiswa_id', $m->id)
                    ->first();
                return ['tanggal' => $sesi->tanggal, 'status' => $absensi ? $absensi->status : 'alpha'];
            });

            $hadir = $detail->where('status', 'hadir')->count();
            $alpha = $detail->where('status', 'alpha')->count();
            $izin  = $detail->where('status', 'izin')->count();
            $sakit = $detail->where('status', 'sakit')->count();
            $total = $sesis->count();

            return [
                'nim'        => $m->nim,
                'nama'       => $m->nama,
                'hadir'      => $hadir,
                'alpha'      => $alpha,
                'izin'       => $izin,
                'sakit'      => $sakit,
                'total_sesi' => $total,
                'persentase' => $total > 0 ? round(($hadir / $total) * 100) : 0,
            ];
        });

        return [$jadwal, $sesis, $rekap];
    }

    // ── Export Excel ──
    public function exportExcel(Request $request)
    {
        [$jadwal, $sesis, $rekap] = $this->getRekapData($request);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');

        // Header styling
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Header row
        $headers = ['No', 'NIM', 'Nama', 'Hadir', 'Alpha', 'Izin', 'Sakit', 'Total Sesi', '% Hadir', 'Status'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($columns[$i] . '1', $header);
        }
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Column widths
        $widths = ['A' => 5, 'B' => 15, 'C' => 30, 'D' => 8, 'E' => 8, 'F' => 8, 'G' => 8, 'H' => 12, 'I' => 10, 'J' => 15];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Data rows
        foreach ($rekap as $i => $m) {
            $row = $i + 2;
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $m['nim']);
            $sheet->setCellValue('C' . $row, $m['nama']);
            $sheet->setCellValue('D' . $row, $m['hadir']);
            $sheet->setCellValue('E' . $row, $m['alpha']);
            $sheet->setCellValue('F' . $row, $m['izin']);
            $sheet->setCellValue('G' . $row, $m['sakit']);
            $sheet->setCellValue('H' . $row, $m['total_sesi']);
            $sheet->setCellValue('I' . $row, $m['persentase'] . '%');
            $sheet->setCellValue('J' . $row, $m['persentase'] >= 75 ? 'Lulus' : 'Tidak Lulus');

            // Warnai status
            $statusColor = $m['persentase'] >= 75 ? '00AA00' : 'DD0000';
            $sheet->getStyle('J' . $row)->getFont()->getColor()->setRGB($statusColor);
        }

        $namaFile = 'rekap_' . str_replace(' ', '_', $jadwal->kelas->nama) . '_' . str_replace(' ', '_', $jadwal->mataKuliah->nama) . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $namaFile, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── Export PDF ──
    public function exportPdf(Request $request)
    {
        [$jadwal, $sesis, $rekap] = $this->getRekapData($request);

        $pdf = Pdf::loadView('admin.rekap.pdf', [
            'jadwal'        => $jadwal,
            'sesis'         => $sesis,
            'rekap'         => $rekap,
            'tanggalDari'   => $request->tanggal_dari,
            'tanggalSampai' => $request->tanggal_sampai,
        ])->setPaper('a4', 'landscape');

        $namaFile = 'rekap_' . str_replace(' ', '_', $jadwal->kelas->nama) . '_' . str_replace(' ', '_', $jadwal->mataKuliah->nama) . '.pdf';

        return $pdf->download($namaFile);
    }
}