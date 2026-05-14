<?php
namespace App\Models;

use App\Models\ProgramStudi;
use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{
    protected $fillable = ['kode', 'nama'];

    public function programStudis()
    {
        return $this->hasMany(ProgramStudi::class);
    }
}