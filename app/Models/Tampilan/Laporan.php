<?php

namespace App\Models\Tampilan;

use App\Models\Akses\Organisasi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    use HasFactory;

    protected $table = 'laporans';
    
    protected $fillable = [
        'organisasi_id',
        'file_laporan',
        'tahun',
        'judul',
        'deskripsi',
        'status'
    ];

    protected $casts = [
        'tahun' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function organisasi()
    {
        return $this->belongsTo(Organisasi::class, 'organisasi_id');
    }
}