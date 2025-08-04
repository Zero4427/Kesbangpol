<?php

namespace App\Models\Akses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \App\Models\Tampilan\Kajian;
use \App\Models\Dokumentasi\Berita;
use App\Models\Status\Verifikasi;
use App\Models\Tampilan\Laporan;

class Organisasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'pengguna_id',
        'kajian_id',
        'nama_organisasi',
        'tanggal_berdiri',
        'deskripsi_organisasi',
        'logo_organisasi',
        'file_persyaratan',
        'status_verifikasi',
    ];

    protected $casts = [
        'tanggal_berdiri' => 'date',
    ];

    const STATUS_PENDING = 'proses';
    const STATUS_VERIFIED = 'aktif';
    const STATUS_REJECTED = 'tidak aktif';

    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function kajian()
    {
        return $this->belongsTo(Kajian::class);
    }

    public function sosialMedias()
    {
        return $this->hasMany(SosialMedia::class);
    }

    public function strukturPengurus()
    {
        return $this->hasMany(StrukturPengurus::class);
    }

    public function beritas()
    {
        return $this->hasMany(Berita::class);
    }

    public function laporans()
    {
        return $this->hasMany(Laporan::class, 'organisasi_id');
    }

    public function verifikasi()
    {
        return $this->hasOne(Verifikasi::class);
    }

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
        return $query->where('status_verifikasi', self::STATUS_PENDING);
    }

    public function scopeVerified($query)
    {
        return $query->where('status_verifikasi', self::STATUS_VERIFIED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status_verifikasi', self::STATUS_REJECTED);
    }

    public function scopePublicVisible($query)
    {
        return $query->where('status_verifikasi', self::STATUS_VERIFIED);
    }

    public function getStatusDisplayAttribute()
    {
        return match($this->status_verifikasi) {
            self::STATUS_PENDING => 'Menunggu Verifikasi',
            self::STATUS_VERIFIED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => 'Tidak Diketahui'
        };
    }

    public function isVerified()
    {
        return $this->status_verifikasi === self::STATUS_VERIFIED;
    }

    public function isPending()
    {
        return $this->status_verifikasi === self::STATUS_PENDING;
    }

    public function isRejected()
    {
        return $this->status_verifikasi === self::STATUS_REJECTED;
    }

    /**public function setStatus($status, $adminId, $catatanAdmin = null, $linkDriveAdmin = null)
    {
        $this->status = $status;
        $this->admin_id = $adminId;
        $this->catatan_admin = $catatanAdmin;
        $this->link_drive_admin = $linkDriveAdmin;
        $this->tanggal_verifikasi = now();
        $this->save();
    }*/
}