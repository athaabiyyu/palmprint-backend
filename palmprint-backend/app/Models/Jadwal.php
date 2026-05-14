<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    protected $fillable = [
        'semester_id',     
        'kelas_id',
        'mata_kuliah_id',
        'dosen_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'ruangan'
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class);
    }

    public function sesiAbsensis()
    {
        return $this->hasMany(SesiAbsensi::class);
    }

    public function sesiAktif()
    {
        return $this->hasOne(SesiAbsensi::class)->where('is_active', true);
    }
}