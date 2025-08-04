<?php

namespace App\Models\Akses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrukturPengurus extends Model
{
    use HasFactory;

    protected $table = 'struktur_pengurus';

    protected $fillable = [
        'organisasi_id',
        'nama_pengurus',
        'jabatan',
        'nomor_sk',
        'nomor_keanggotaan',
        'no_telpon',
    ];

    public function organisasi()
    {
        return $this->belongsTo(Organisasi::class);
    }
}