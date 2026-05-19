<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesiAbsensi extends Model
{
    protected $fillable = [
        'jadwal_id',
        'tanggal',
        'dibuka_at',
        'ditutup_at',
        'durasi_menit',
        'is_active'
    ];

    protected $casts = [
        'dibuka_at'  => 'datetime',
        'ditutup_at' => 'datetime',
        'tanggal'    => 'date',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function absensis()
    {
        return $this->hasMany(Absensi::class);
    }

    public function surats()
    {
        return $this->hasMany(Surat::class);
    }
}
