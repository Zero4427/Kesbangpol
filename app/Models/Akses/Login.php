<?php

namespace App\Models\Akses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Login extends Model
{
    use HasFactory;

    protected $table = 'logins';

    protected $fillable = [
        'user_id',
        'user_type',
        'email',
        'bearer_token',
        'role',
        'login_at',
        'last_activity',
        'expires_at',
        'is_active',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Relationship dengan model Admin
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'user_id', 'id');
                   //->where('user_type', 'admin');
    }

    /**
     * Relationship dengan model Pengguna
     */
    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class, 'user_id', 'id');
                   //->where('user_type', 'pengguna');
    }

    /**
     * Scope untuk session aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope untuk session expired
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope untuk filter berdasarkan user type
     */
    public function scopeByUserType($query, $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope untuk filter berdasarkan role
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Accessor untuk mendapatkan nama user
     */
    public function getUserNameAttribute()
    {
        if ($this->user_type === 'admin' && $this->admin) {
            return $this->admin->nama_admin;
        } elseif ($this->user_type === 'pengguna' && $this->pengguna) {
            return $this->pengguna->nama_pengguna;
        }
        
        return 'N/A';
    }

    /**
     * Accessor untuk mendapatkan nama role
     */
    public function getRoleNameAttribute()
    {
        switch ($this->role) {
            case 'admin':
                return 'Admin';
            case 'pengguna':
                return 'Pengguna';
            default:
                return 'Unknown';
        }
    }

    /**
     * Accessor untuk menghitung durasi session
     */
    public function getSessionDurationAttribute()
    {
        if (!$this->login_at || !$this->last_activity) {
            return 'N/A';
        }

        $duration = $this->login_at->diffInMinutes($this->last_activity);
        
        if ($duration < 60) {
            return $duration . ' minutes';
        } elseif ($duration < 1440) {
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            return $hours . 'h ' . $minutes . 'm';
        } else {
            $days = floor($duration / 1440);
            $hours = floor(($duration % 1440) / 60);
            return $days . 'd ' . $hours . 'h';
        }
    }

    /**
     * Accessor untuk status session
     */
    public function getStatusAttribute()
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->expires_at < now()) {
            return 'Expired';
        }

        // Cek apakah session masih aktif (last activity dalam 30 menit terakhir)
        if ($this->last_activity && $this->last_activity->diffInMinutes(now()) > 30) {
            return 'Idle';
        }

        return 'Active';
    }

    /**
     * Method untuk memperpanjang session
     */
    public function extendSession($minutes = null)
    {
        if ($minutes === null) {
            // Default extension berdasarkan user type
            $minutes = $this->user_type === 'admin' ? 480 : 43200; // 8 jam untuk admin, 30 hari untuk pengguna
        }

        $this->update([
            'expires_at' => now()->addMinutes($minutes),
            'last_activity' => now()
        ]);

        return $this;
    }

    /**
     * Method untuk terminate session
     */
    public function terminate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    /**
     * Method untuk cek apakah session masih valid
     */
    public function isValid()
    {
        return $this->is_active && $this->expires_at > now();
    }

    /**
     * Static method untuk cleanup expired sessions
     */
    public static function cleanupExpired()
    {
        return static::where('expires_at', '<', now())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
    }

    /**
     * Static method untuk mendapatkan statistics
     */
    public static function getStatistics()
    {
        return [
            'total_active' => static::active()->count(),
            'admin_active' => static::active()->byUserType('admin')->count(),
            'pengguna_active' => static::active()->byUserType('pengguna')->count(),
            'expired_sessions' => static::expired()->where('is_active', true)->count(),
            'sessions_today' => static::whereDate('login_at', today())->count(),
            'sessions_this_week' => static::whereBetween('login_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count()
        ];
    }

    /**
     * Boot method untuk auto cleanup saat membuat session baru
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($login) {
            // Set default values
            if (empty($login->login_at)) {
                $login->login_at = now();
            }
            
            if (empty($login->last_activity)) {
                $login->last_activity = now();
            }
        });

        // Auto cleanup expired sessions secara periodik
        static::created(function ($login) {
            // Cleanup expired sessions (jalankan hanya 10% dari waktu untuk menghindari overhead)
            if (rand(1, 10) === 1) {
                static::cleanupExpired();
            }
        });
    }
}