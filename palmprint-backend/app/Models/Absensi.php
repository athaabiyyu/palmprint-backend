<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    protected $fillable = [
        'sesi_absensi_id', 'mahasiswa_id',
        'waktu_absen', 'similarity_score', 'status'
    ];

    protected $casts = [
        'waktu_absen' => 'datetime',
    ];

    public function sesiAbsensi()
    {
        return $this->belongsTo(SesiAbsensi::class);
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}