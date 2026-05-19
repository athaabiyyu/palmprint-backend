<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Surat extends Model
{
    protected $fillable = [
        'mahasiswa_id',
        'sesi_absensi_id',
        'jenis',
        'link_drive',
        'keterangan',
        'status',
        'catatan_admin',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function sesiAbsensi()
    {
        return $this->belongsTo(SesiAbsensi::class);
    }
}