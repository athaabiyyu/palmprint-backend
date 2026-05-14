<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataKuliah extends Model
{
    protected $fillable = ['semester_id', 'kode', 'nama', 'sks'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }
}