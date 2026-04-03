<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Mahasiswa extends Model
{
    use HasApiTokens;

    protected $fillable = ['nim', 'nama'];

    public function palmprintTemplates()
    {
        return $this->hasMany(PalmprintTemplate::class);
    }
}
