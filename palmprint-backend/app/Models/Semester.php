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
}