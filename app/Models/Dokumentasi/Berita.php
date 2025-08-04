<?php

namespace App\Models\Dokumentasi;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    use HasFactory;

    protected $fillable = [
        'pengguna_id',
        'organisasi_id',
        'nama_kegiatan',
        'lokasi_kegiatan',
        'tanggal_kegiatan',
        'deskripsi_kegiatan',
        'dokumentasi_kegiatan',
        'is_approved',
    ];

    protected $casts = [
        'tanggal_kegiatan' => 'date',
        'dokumentasi_kegiatan' => 'array',
        'is_approved' => 'boolean',
    ];

    public function pengguna()
    {
        return $this->belongsTo(\App\Models\Akses\Pengguna::class);
    }

    public function organisasi()
    {
        return $this->belongsTo(\App\Models\Akses\Organisasi::class);
    }
}