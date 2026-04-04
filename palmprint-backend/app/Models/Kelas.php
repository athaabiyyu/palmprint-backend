<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    protected $fillable = ['nama', 'semester_id'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
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