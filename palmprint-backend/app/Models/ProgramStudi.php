<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramStudi extends Model
{
    protected $fillable = ['jurusan_id', 'kode', 'nama'];

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'prodi_id');
    }

    public function mataKuliahs()
    {
        return $this->hasMany(MataKuliah::class, 'prodi_id');
    }
}