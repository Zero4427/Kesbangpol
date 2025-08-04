<?php

namespace App\Models\Akses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SosialMedia extends Model
{
    use HasFactory;

    protected $table = 'sosial_medias';

    protected $fillable = [
        'organisasi_id',
        'platform',
        'url',
    ];

    public function organisasi()
    {
        return $this->belongsTo(Organisasi::class);
    }
}