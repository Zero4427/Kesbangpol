<?php

namespace App\Models\Tampilan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'pertanyaan',
        'jawaban',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}