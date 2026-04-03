<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalmprintTemplate extends Model
{
    protected $fillable = ['mahasiswa_id', 'feature_vector', 'sample_index'];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}