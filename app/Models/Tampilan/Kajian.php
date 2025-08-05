<?php

namespace App\Models\Tampilan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Akses\Organisasi;

class Kajian extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_kajian',
        'deskripsi_kajian',
    ];

    public function organisasi()
    {
        return $this->hasMany(Organisasi::class);
    }
}