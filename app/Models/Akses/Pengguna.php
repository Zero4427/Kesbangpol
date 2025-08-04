<?php

namespace App\Models\Akses;

use App\Models\Dokumentasi\Berita;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nama_pengguna',
        'email_pengguna',
        'password_pengguna',
        'alamat_pengguna',
        'no_telpon_pengguna',
    ];

    protected $hidden = [
        'password_pengguna',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_pengguna' => 'hashed',
    ];

    public function getAuthPassword()
    {
        return $this->password_pengguna;
    }

    public function getEmailForPasswordReset()
    {
        return $this->email_pengguna;
    }

    public function organisasi()
    {
        return $this->hasMany(Organisasi::class);
    }

    public function beritas()
    {
        return $this->hasMany(Berita::class);
    }
}