<?php

namespace App\Models\Tampilan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bidang extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_ketua',
        'nama_bidang',
        'deskripsi_bidang',
        'gambar_karyawan',
        'jumlah_staf',
    ];

    protected $casts = [
        'jumlah_staf' => 'integer',
    ];
}