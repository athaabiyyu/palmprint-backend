<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $fillable = ['nama', 'tahun_ajaran', 'tipe', 'is_active'];

    public function kelas()
    {
        return $this->hasMany(Kelas::class);
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }

    public static function aktif()
    {
        return static::where('is_active', true)->first();
    }

    public function mataKuliahs()
    {
        return $this->hasMany(MataKuliah::class);
    }
}
