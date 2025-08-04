<?php

namespace App\Models\Tampilan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kajian extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_kajian',
        'deskripsi_kajian',
    ];

    public function organisasi()
    {
        return $this->hasMany(\App\Models\Akses\Organisasi::class);
    }
}