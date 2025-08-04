<?php

namespace App\Models\Akses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    use HasFactory;

    protected $table = 'password_reset_otps';

    protected $fillable = [
        'email',
        'otp_code',
        'user_type',
        'expires_at',
        'is_used',
        'is_active',
        'ip_address'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Scope untuk OTP yang masih valid (aktif dan belum expired)
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
                    ->where('is_used', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope untuk OTP yang sudah expired
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope untuk filter berdasarkan email dan user type
     */
    public function scopeForUser($query, $email, $userType)
    {
        return $query->where('email', $email)
                    ->where('user_type', $userType);
    }

    /**
     * Method untuk mengecek apakah OTP masih valid
     */
    public function isValid()
    {
        return $this->is_active 
            && !$this->is_used 
            && $this->expires_at > now();
    }

    /**
     * Method untuk menandai OTP sebagai sudah digunakan
     */
    public function markAsUsed()
    {
        $this->update([
            'is_used' => true,
            'is_active' => false
        ]);
        
        return $this;
    }

    /**
     * Method untuk menonaktifkan OTP
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    /**
     * Accessor untuk mendapatkan status OTP
     */
    public function getStatusAttribute()
    {
        if ($this->is_used) {
            return 'Used';
        }
        
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        if ($this->expires_at < now()) {
            return 'Expired';
        }
        
        return 'Active';
    }

    /**
     * Accessor untuk mendapatkan waktu tersisa sampai expired
     */
    public function getTimeRemainingAttribute()
    {
        if ($this->expires_at < now()) {
            return 'Expired';
        }
        
        $diff = now()->diffInMinutes($this->expires_at);
        
        if ($diff < 1) {
            return 'Less than 1 minute';
        }
        
        return $diff . ' minutes';
    }

    /**
     * Static method untuk generate OTP code (6 digit)
     */
    public static function generateOtpCode()
    {
        return sprintf('%06d', mt_rand(100000, 999999));
    }

    /**
     * Static method untuk cleanup expired OTP
     */
    public static function cleanupExpired()
    {
        return static::expired()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
    }

    /**
     * Static method untuk mendapatkan statistik OTP
     */
    public static function getStatistics()
    {
        return [
            'total_active' => static::where('is_active', true)->count(),
            'total_used' => static::where('is_used', true)->count(),
            'total_expired' => static::expired()->count(),
            'requests_today' => static::whereDate('created_at', today())->count(),
            'success_rate' => static::getSuccessRate()
        ];
    }

    /**
     * Static method untuk menghitung success rate
     */
    public static function getSuccessRate()
    {
        $total = static::count();
        
        if ($total === 0) {
            return 0;
        }
        
        $used = static::where('is_used', true)->count();
        
        return round(($used / $total) * 100, 2);
    }

    /**
     * Boot method untuk auto cleanup
     */
    protected static function boot()
    {
        parent::boot();

        // Auto cleanup expired OTP saat membuat OTP baru
        static::created(function ($otp) {
            // Cleanup expired OTP (jalankan hanya 20% dari waktu untuk menghindari overhead)
            if (rand(1, 5) === 1) {
                static::cleanupExpired();
            }
        });
    }
}