<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataKuliah extends Model
{
    protected $fillable = ['prodi_id', 'semester_id', 'kode', 'nama', 'sks'];

    public function prodi()
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }
}