<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Dosen extends Model
{
    use HasApiTokens;

    protected $fillable = ['nip', 'nama', 'password', 'is_active'];
    protected $hidden   = ['password'];

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }
}