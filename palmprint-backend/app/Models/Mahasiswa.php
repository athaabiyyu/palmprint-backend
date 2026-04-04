<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Mahasiswa extends Model
{
    use HasApiTokens;

    protected $table    = 'mahasiswas';
    protected $fillable = ['nim', 'nama', 'password'];
    protected $hidden   = ['password'];

    public function palmprintTemplates()
    {
        return $this->hasMany(PalmprintTemplate::class);
    }

    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'mahasiswa_kelas');
    }

    public function absensis()
    {
        return $this->hasMany(Absensi::class);
    }
}