<?php

namespace App\Models\Tampilan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TentangKami extends Model
{
    use HasFactory;

    protected $fillable = [
        'deskripsi',
        'gambar',
    ];
}