<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    protected $fillable = ['prodi_id', 'semester_id', 'kode', 'nama', 'sks'];

    public function prodi()
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function mahasiswas()
    {
        return $this->belongsToMany(Mahasiswa::class, 'mahasiswa_kelas');
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }
}
