<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Akses\Admin;
use App\Models\Akses\Organisasi;

class Verifikasi extends Model
{
    use HasFactory;

    protected $table = 'verifikasis';

    protected $fillable = [
        'organisasi_id',
        'admin_id',
        'status',
        'catatan_admin',
        'link_drive_admin',
        'tanggal_verifikasi',
    ];

    protected $casts = [
        'tanggal_verifikasi' => 'datetime',
    ];

    const STATUS_PENDING = 'proses';
    const STATUS_APPROVED = 'aktif';
    const STATUS_REJECTED = 'tidak aktif';

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function organisasi()
    {
        return $this->belongsTo(Organisasi::class, 'organisasi_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Menunggu Verifikasi',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => 'Tidak Diketahui'
        };
    }

    public function setStatus($status, $adminId, $catatanAdmin = null, $linkDriveAdmin = null)
    {
        $this->status = $status;
        $this->admin_id = $adminId;
        $this->catatan_admin = $catatanAdmin;
        $this->link_drive_admin = $linkDriveAdmin;
        $this->tanggal_verifikasi = now();
        $this->save();
    }
}