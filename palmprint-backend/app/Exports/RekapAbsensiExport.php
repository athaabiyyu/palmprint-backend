<?php
namespace App\Exports;

use Illuminate\Support\Collection;

class RekapAbsensiExport
{
    protected $rekap;
    protected $jadwal;
    protected $sesis;

    public function __construct($rekap, $jadwal, $sesis)
    {
        $this->rekap  = $rekap;
        $this->jadwal = $jadwal;
        $this->sesis  = $sesis;
    }

    public function collection(): Collection
    {
        return collect($this->rekap)->map(function ($m, $i) {
            return [
                'No'         => $i + 1,
                'NIM'        => $m['nim'],
                'Nama'       => $m['nama'],
                'Hadir'      => $m['hadir'],
                'Alpha'      => $m['alpha'],
                'Izin'       => $m['izin'],
                'Sakit'      => $m['sakit'],
                'Total Sesi' => $m['total_sesi'],
                '% Hadir'    => $m['persentase'] . '%',
                'Status'     => $m['persentase'] >= 75 ? 'Lulus' : 'Tidak Lulus',
            ];
        });
    }
}