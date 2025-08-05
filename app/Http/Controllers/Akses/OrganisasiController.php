<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Akses\Organisasi;
use App\Models\Akses\SosialMedia;
use App\Models\Akses\StrukturPengurus;
use App\Models\Status\Verifikasi;
use App\Models\Akses\Login;
use App\Models\Akses\Admin;
use App\Models\Akses\Pengguna;
use App\Models\Tampilan\Kajian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganisasiController extends Controller
{
    // Method untuk public tanpa bearer token (hanya organisasi terverifikasi)
    public function indexPublic()
    {
        try {
            $organisasi = Organisasi::with(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus'])
                ->where('status_verifikasi', Organisasi::STATUS_VERIFIED)
                ->latest()
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data organisasi berhasil diambil',
                'data' => $organisasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Method untuk public tanpa bearer token (detail organisasi terverifikasi)
    public function showPublic($id)
    {
        try {
            $organisasi = Organisasi::with(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus'])
                ->where('status_verifikasi', Organisasi::STATUS_VERIFIED)
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Data organisasi berhasil diambil',
                'data' => $organisasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Organisasi tidak ditemukan atau belum terverifikasi',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function index(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            $query = Organisasi::with(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus', 'verifikasi']);
            
            // Filter berdasarkan role user
            if ($currentLogin->user_type === 'pengguna') {
                // Pengguna biasa hanya bisa lihat organisasi terverifikasi dan milik sendiri
                $query->where(function($q) use ($currentLogin) {
                    $q->where('status_verifikasi', Organisasi::STATUS_VERIFIED)
                      ->orWhere('pengguna_id', $currentLogin->user_id);
                });
            }
            // Admin bisa lihat semua organisasi

            // Filter berdasarkan status jika diminta
            if ($request->has('status')) {
                $status = $request->input('status');
                if (in_array($status, [Organisasi::STATUS_PENDING, Organisasi::STATUS_VERIFIED, Organisasi::STATUS_REJECTED])) {
                    $query->where('status_verifikasi', $status);
                }
            }

            $organisasi = $query->latest()->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data organisasi berhasil diambil',
                'data' => $organisasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== NEW METHODS FOR ADMIN MANAGEMENT =====
    public function getAllOrganisasiForAdmin(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            // Pastikan yang mengakses adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
                ], 403);
            }

            $query = Organisasi::with([
                'pengguna:id,nama_pengguna,email_pengguna,no_telpon_pengguna',
                'kajian:id,nama_kajian',
                'sosialMedias',
                'strukturPengurus',
                'verifikasi.admin:id,nama_admin'
            ]);

            // Filter berdasarkan status jika diminta
            if ($request->has('status')) {
                $status = $request->input('status');
                if (in_array($status, [Organisasi::STATUS_PENDING, Organisasi::STATUS_VERIFIED, Organisasi::STATUS_REJECTED])) {
                    $query->where('status_verifikasi', $status);
                }
            }

            // Filter berdasarkan kajian jika diminta
            if ($request->has('kajian_id')) {
                $query->where('kajian_id', $request->kajian_id);
            }

            // Filter berdasarkan tanggal pendaftaran
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            if (in_array($sortBy, ['created_at', 'nama_organisasi', 'status_verifikasi', 'tanggal_berdiri'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->latest();
            }

            // Pagination
            $perPage = $request->input('per_page', 15);
            $perPage = min($perPage, 100); // Max 100 per page

            if ($request->has('paginate') && $request->paginate === 'false') {
                $organisasi = $query->get();
                $totalCount = $organisasi->count();
            } else {
                $organisasi = $query->paginate($perPage);
                $totalCount = $organisasi->total();
            }

            // Statistik ringkas
            $stats = [
                'total' => Organisasi::count(),
                'aktif' => Organisasi::where('status_verifikasi', Organisasi::STATUS_VERIFIED)->count(),
                'proses' => Organisasi::where('status_verifikasi', Organisasi::STATUS_PENDING)->count(),
                'tidak_aktif' => Organisasi::where('status_verifikasi', Organisasi::STATUS_REJECTED)->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data semua organisasi berhasil diambil',
                'data' => $organisasi,
                'statistics' => $stats,
                'filters_applied' => [
                    'status' => $request->input('status'),
                    'kajian_id' => $request->input('kajian_id'),
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrganisasiByStatus(Request $request, $status)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            // Pastikan yang mengakses adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
                ], 403);
            }

            // Validasi status
            if (!in_array($status, [Organisasi::STATUS_PENDING, Organisasi::STATUS_VERIFIED, Organisasi::STATUS_REJECTED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status tidak valid. Status yang tersedia: proses, aktif, tidak aktif'
                ], 400);
            }

            $organisasi = Organisasi::with([
                'pengguna:id,nama_pengguna,email_pengguna,no_telpon_pengguna',
                'kajian:id,nama_kajian',
                'sosialMedias',
                'strukturPengurus',
                'verifikasi.admin:id,nama_admin'
            ])
            ->where('status_verifikasi', $status)
            ->latest()
            ->get();

            $statusMessages = [
                Organisasi::STATUS_PENDING => 'Organisasi yang menunggu verifikasi',
                Organisasi::STATUS_VERIFIED => 'Organisasi yang telah diverifikasi (aktif)',
                Organisasi::STATUS_REJECTED => 'Organisasi yang ditolak (tidak aktif)'
            ];

            return response()->json([
                'success' => true,
                'message' => $statusMessages[$status],
                'data' => $organisasi,
                'count' => $organisasi->count(),
                'status_filter' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDetailForAdmin(Request $request, $id)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            // Pastikan yang mengakses adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
                ], 403);
            }

            $organisasi = Organisasi::with([
                'pengguna' => function($query) {
                    $query->select('id', 'nama_pengguna', 'email_pengguna', 'alamat_pengguna', 'no_telpon_pengguna', 'created_at');
                },
                'kajian:id,nama_kajian,deskripsi_kajian',
                'sosialMedias',
                'strukturPengurus',
                'verifikasi' => function($query) {
                    $query->with('admin:id,nama_admin,email_admin');
                }
            ])
            ->findOrFail($id);

            // Tambahan informasi untuk admin
            $additionalInfo = [
                'file_urls' => [
                    'logo_url' => $organisasi->logo_organisasi ? Storage::url($organisasi->logo_organisasi) : null,
                    'persyaratan_url' => $organisasi->file_persyaratan ? Storage::url($organisasi->file_persyaratan) : null,
                ],
                'verification_history' => $organisasi->verifikasi ? [
                    'verified_by' => $organisasi->verifikasi->admin->nama_admin ?? null,
                    'verified_at' => $organisasi->verifikasi->tanggal_verifikasi,
                    'admin_notes' => $organisasi->verifikasi->catatan_admin,
                    'admin_drive_link' => $organisasi->verifikasi->link_drive_admin,
                ] : null,
                'status_display' => $organisasi->getStatusDisplayAttribute(),
                'days_since_registration' => $organisasi->created_at->diffInDays(now()),
                'last_updated' => $organisasi->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detail organisasi berhasil diambil',
                'data' => $organisasi,
                'additional_info' => $additionalInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Organisasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function getOrganisasiStatistics(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            // Pastikan yang mengakses adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
                ], 403);
            }

            // Statistik dasar
            $basicStats = [
                'total_organisasi' => Organisasi::count(),
                'organisasi_aktif' => Organisasi::where('status_verifikasi', Organisasi::STATUS_VERIFIED)->count(),
                'menunggu_verifikasi' => Organisasi::where('status_verifikasi', Organisasi::STATUS_PENDING)->count(),
                'organisasi_ditolak' => Organisasi::where('status_verifikasi', Organisasi::STATUS_REJECTED)->count(),
            ];

            // Statistik berdasarkan kajian
            $statsByKajian = Kajian::withCount([
                'organisasi',
                'organisasi as organisasi_aktif_count' => function($query) {
                    $query->where('status_verifikasi', Organisasi::STATUS_VERIFIED);
                },
                'organisasi as organisasi_pending_count' => function($query) {
                    $query->where('status_verifikasi', Organisasi::STATUS_PENDING);
                }
            ])->get();

            // Statistik berdasarkan bulan registrasi (6 bulan terakhir)
            $monthlyStats = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthlyStats[] = [
                    'month' => $date->format('Y-m'),
                    'month_name' => $date->format('F Y'),
                    'total_registered' => Organisasi::whereMonth('created_at', $date->month)
                                                   ->whereYear('created_at', $date->year)
                                                   ->count(),
                    'verified_count' => Organisasi::whereMonth('created_at', $date->month)
                                                 ->whereYear('created_at', $date->year)
                                                 ->where('status_verifikasi', Organisasi::STATUS_VERIFIED)
                                                 ->count(),
                ];
            }

            // Organisasi terbaru yang butuh perhatian admin
            $recentPending = Organisasi::with(['pengguna:id,nama_pengguna', 'kajian:id,nama_kajian'])
                ->where('status_verifikasi', Organisasi::STATUS_PENDING)
                ->latest()
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Statistik organisasi berhasil diambil',
                'data' => [
                    'basic_statistics' => $basicStats,
                    'statistics_by_kajian' => $statsByKajian,
                    'monthly_registration' => $monthlyStats,
                    'recent_pending_organizations' => $recentPending,
                    'generated_at' => now()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kajian' => 'required|string|exists:kajians,nama_kajian', // Menggunakan nama kajian
            'tanggal_berdiri' => 'required|date',
            'deskripsi_organisasi' => 'required|string',
            'logo_organisasi' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'file_persyaratan' => 'required|file|mimes:pdf|max:5120', // Wajib untuk verifikasi
            'sosial_media' => 'nullable|array',
            'sosial_media.*.platform' => 'required_with:sosial_media|string',
            'sosial_media.*.url' => 'required_with:sosial_media|url',
            'struktur_pengurus' => 'required|array|min:2',
            'struktur_pengurus.*.nama_pengurus' => 'required|string|max:255',
            'struktur_pengurus.*.jabatan' => 'required|string|max:255',
            'struktur_pengurus.*.nomor_sk' => 'nullable|string|max:100',
            'struktur_pengurus.*.nomor_keanggotaan' => 'nullable|string|max:100',
            'struktur_pengurus.*.no_telpon' => 'nullable|string|max:15',
        ], [
            // Custom error messages
            'nama_kajian.required' => 'Kajian harus dipilih.',
            'nama_kajian.exists' => 'Kajian yang dipilih tidak valid.',
            'file_persyaratan.required' => 'File persyaratan wajib diunggah untuk verifikasi.',
            'struktur_pengurus.min' => 'Minimal harus ada 2 pengurus dalam struktur organisasi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $currentLogin = $this->getCurrentLogin($request);
            
            // Ambil data pengguna untuk nama organisasi
            $pengguna = Pengguna::find($currentLogin->user_id);
            $namaOrganisasi = $pengguna->nama_pengguna;
            
            // Cek apakah nama organisasi sudah ada
            $existingOrganisasi = Organisasi::where('nama_organisasi', $namaOrganisasi)->first();
            if ($existingOrganisasi) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Nama organisasi sudah terdaftar dalam sistem. Tidak dapat menggunakan nama pengguna yang sama.',
                    'errors' => [
                        'nama_organisasi' => ['Nama organisasi "' . $namaOrganisasi . '" sudah terdaftar.']
                    ]
                ], 422);
            }

            // Cari kajian berdasarkan nama
            $kajian = Kajian::where('nama_kajian', $request->nama_kajian)->first();
            
            $logoPath = null;
            if ($request->hasFile('logo_organisasi')) {
                $logoPath = $request->file('logo_organisasi')->store('logos', 'public');
            }

            $filePath = null;
            if ($request->hasFile('file_persyaratan')) {
                $filePath = $request->file('file_persyaratan')->store('persyaratan', 'public');
            }

            // Buat organisasi dengan status pending
            $organisasi = Organisasi::create([
                'pengguna_id' => $currentLogin->user_id,
                'kajian_id' => $kajian->id, // Menggunakan ID dari nama kajian
                'nama_organisasi' => $namaOrganisasi, // Otomatis menggunakan nama pengguna
                'tanggal_berdiri' => $request->tanggal_berdiri,
                'deskripsi_organisasi' => $request->deskripsi_organisasi,
                'logo_organisasi' => $logoPath,
                'file_persyaratan' => $filePath,
                'status_verifikasi' => Organisasi::STATUS_PENDING, // Otomatis pending
            ]);

            // Buat entri verifikasi otomatis
            Verifikasi::create([
                'organisasi_id' => $organisasi->id,
                'status' => Verifikasi::STATUS_PENDING,
                'tanggal_verifikasi' => null,
            ]);

            // Save sosial media
            if ($request->has('sosial_media')) {
                foreach ($request->sosial_media as $sosmed) {
                    SosialMedia::create([
                        'organisasi_id' => $organisasi->id,
                        'platform' => $sosmed['platform'],
                        'url' => $sosmed['url'],
                    ]);
                }
            }

            // Save struktur pengurus
            foreach ($request->struktur_pengurus as $pengurus) {
                StrukturPengurus::create([
                    'organisasi_id' => $organisasi->id,
                    'nama_pengurus' => $pengurus['nama_pengurus'],
                    'jabatan' => $pengurus['jabatan'],
                    'nomor_sk' => $pengurus['nomor_sk'] ?? null,
                    'nomor_keanggotaan' => $pengurus['nomor_keanggotaan'] ?? null,
                    'no_telpon' => $pengurus['no_telpon'] ?? null,
                ]);
            }

            $organisasi->load(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus', 'verifikasi']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Organisasi berhasil dibuat dengan nama "' . $namaOrganisasi . '" dan menunggu verifikasi admin',
                'data' => $organisasi
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id, Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            $organisasi = Organisasi::with(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus', 'verifikasi.admin'])
                ->findOrFail($id);
            
            // Cek permission untuk melihat organisasi
            if ($currentLogin->user_type === 'pengguna') {
                // Pengguna hanya bisa lihat organisasi terverifikasi atau milik sendiri
                if ($organisasi->status_verifikasi !== Organisasi::STATUS_VERIFIED && 
                    $organisasi->pengguna_id !== $currentLogin->user_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Organisasi tidak dapat diakses'
                    ], 403);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data organisasi berhasil diambil',
                'data' => $organisasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Organisasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            Log::info('Update request received', ['id' => $id, 'data' => $request->all()]);

            // Cari organisasi
            $organisasi = Organisasi::findOrFail($id);
            Log::info('Organisasi found', ['id' => $id, 'organisasi' => $organisasi->toArray()]);

            // Validasi token dan izin pengguna
            $currentLogin = $this->getCurrentLogin($request);
            if (!$currentLogin) {
                Log::error('Invalid or missing token', ['request' => $request->headers->all()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau tidak ditemukan'
                ], 401);
            }

            // Cek apakah pengguna memiliki izin
            if ($currentLogin->user_type === 'pengguna' && $currentLogin->user_id !== $organisasi->pengguna_id) {
                Log::error('Unauthorized access attempt', ['user_id' => $currentLogin->user_id, 'organisasi_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak diizinkan: Anda bukan pemilik organisasi ini'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'nama_kajian' => 'sometimes|string|exists:kajians,nama_kajian',
                'deskripsi_organisasi' => 'sometimes|string',
                'logo_organisasi' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'file_persyaratan' => 'nullable|file|mimes:pdf|max:5120',
                'sosial_media' => 'nullable|array',
                'sosial_media.*.platform' => 'required_with:sosial_media|string',
                'sosial_media.*.url' => 'required_with:sosial_media|url',
                'struktur_pengurus' => 'sometimes|array|min:2',
                'struktur_pengurus.*.nama_pengurus' => 'required_with:struktur_pengurus|string|max:255',
                'struktur_pengurus.*.jabatan' => 'required_with:struktur_pengurus|string|max:255',
                'struktur_pengurus.*.nomor_sk' => 'nullable|string|max:100',
                'struktur_pengurus.*.nomor_keanggotaan' => 'nullable|string|max:100',
                'struktur_pengurus.*.no_telpon' => 'nullable|string|max:15',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Handle logo upload
            if ($request->hasFile('logo_organisasi')) {
                if ($organisasi->logo_organisasi) {
                    Storage::disk('public')->delete($organisasi->logo_organisasi);
                    Log::info('Old logo deleted', ['path' => $organisasi->logo_organisasi]);
                }
                $organisasi->logo_organisasi = $request->file('logo_organisasi')->store('logos', 'public');
                Log::info('New logo uploaded', ['path' => $organisasi->logo_organisasi]);
            }

            // Handle file upload
            if ($request->hasFile('file_persyaratan')) {
                if ($organisasi->file_persyaratan) {
                    Storage::disk('public')->delete($organisasi->file_persyaratan);
                    Log::info('Old file persyaratan deleted', ['path' => $organisasi->file_persyaratan]);
                }
                $organisasi->file_persyaratan = $request->file('file_persyaratan')->store('persyaratan', 'public');
                Log::info('New file persyaratan uploaded', ['path' => $organisasi->file_persyaratan]);
                // Reset status verifikasi jika file persyaratan diupdate
                $organisasi->status_verifikasi = Organisasi::STATUS_PENDING;
                if ($organisasi->verifikasi) {
                    $organisasi->verifikasi->update(['status' => Verifikasi::STATUS_PENDING, 'tanggal_verifikasi' => null]);
                }
            }

            // Siapkan data untuk update (kecuali nama_organisasi dan tanggal_berdiri)
            $updateData = $request->only(['deskripsi_organisasi']);
            Log::info('Update data prepared', ['updateData' => $updateData]);

            // Update kajian jika ada perubahan nama_kajian
            if ($request->has('nama_kajian')) {
                $kajian = Kajian::where('nama_kajian', $request->nama_kajian)->first();
                if ($kajian) {
                    $updateData['kajian_id'] = $kajian->id;
                    Log::info('Kajian found and will be updated', ['kajian_id' => $kajian->id]);
                } else {
                    DB::rollback();
                    Log::error('Kajian not found', ['nama_kajian' => $request->nama_kajian]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Kajian tidak ditemukan',
                        'errors' => ['nama_kajian' => ['Kajian tidak ditemukan.']]
                    ], 422);
                }
            }

            // Update organisasi hanya jika ada data untuk diupdate
            if (!empty($updateData)) {
                $organisasi->update($updateData);
                Log::info('Organisasi updated', ['id' => $id, 'updated_data' => $updateData]);
            } else {
                Log::info('No fields to update in organisasi table', ['id' => $id]);
            }

            // Update sosial media
            if ($request->has('sosial_media')) {
                $organisasi->sosialMedias()->delete();
                Log::info('Existing sosial media deleted', ['organisasi_id' => $id]);
                foreach ($request->sosial_media as $sosmed) {
                    SosialMedia::create([
                        'organisasi_id' => $organisasi->id,
                        'platform' => $sosmed['platform'],
                        'url' => $sosmed['url'],
                    ]);
                }
                Log::info('New sosial media created', ['organisasi_id' => $id, 'sosial_media' => $request->sosial_media]);
            }

            // Update struktur pengurus
            if ($request->has('struktur_pengurus')) {
                $organisasi->strukturPengurus()->delete();
                Log::info('Existing struktur pengurus deleted', ['organisasi_id' => $id]);
                foreach ($request->struktur_pengurus as $pengurus) {
                    StrukturPengurus::create([
                        'organisasi_id' => $organisasi->id,
                        'nama_pengurus' => $pengurus['nama_pengurus'],
                        'jabatan' => $pengurus['jabatan'],
                        'nomor_sk' => $pengurus['nomor_sk'] ?? null,
                        'nomor_keanggotaan' => $pengurus['nomor_keanggotaan'] ?? null,
                        'no_telpon' => $pengurus['no_telpon'] ?? null,
                    ]);
                }
                Log::info('New struktur pengurus created', ['organisasi_id' => $id, 'struktur_pengurus' => $request->struktur_pengurus]);
            }

            // Simpan perubahan file (logo_organisasi dan file_persyaratan) jika ada
            if ($request->hasFile('logo_organisasi') || $request->hasFile('file_persyaratan')) {
                $organisasi->save();
                Log::info('Organisasi saved with file updates', ['id' => $id, 'logo' => $organisasi->logo_organisasi, 'persyaratan' => $organisasi->file_persyaratan]);
            }

            // Muat ulang relasi untuk respons
            $organisasi->load(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus']);
            Log::info('Organisasi loaded after update', ['organisasi' => $organisasi->toArray()]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Organisasi berhasil diupdate',
                'data' => $organisasi
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $organisasi = Organisasi::findOrFail($id);
            $currentLogin = $this->getCurrentLogin($request);

            // Check if user owns this organisasi or is admin
            if ($currentLogin->user_type === 'pengguna' && $currentLogin->user_id !== $organisasi->pengguna_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Delete associated files
            if ($organisasi->logo_organisasi) {
                Storage::disk('public')->delete($organisasi->logo_organisasi);
            }
            if ($organisasi->file_persyaratan) {
                Storage::disk('public')->delete($organisasi->file_persyaratan);
            }

            $organisasi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Organisasi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateVerifikasi(Request $request, $id)
    {
        $currentLogin = $this->getCurrentLogin($request);
        
        // Cek apakah user adalah admin
        if ($currentLogin->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat melakukan verifikasi'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status_verifikasi' => 'required|in:aktif,tidak aktif',
            'catatan_admin' => 'nullable|string|max:1000',
            'link_drive_admin' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $organisasi = Organisasi::with('verifikasi')->findOrFail($id);
            
            // Update status organisasi
            $organisasi->update(['status_verifikasi' => $request->status_verifikasi]);

            // Update atau buat verifikasi
            if ($organisasi->verifikasi) {
                $organisasi->verifikasi->update([
                    'status' => $request->status_verifikasi,
                    'admin_id' => $currentLogin->user_id,
                    'catatan_admin' => $request->catatan_admin,
                    'link_drive_admin' => $request->link_drive_admin,
                    'tanggal_verifikasi' => now(),
                ]);
            } else {
                Verifikasi::create([
                    'organisasi_id' => $organisasi->id,
                    'admin_id' => $currentLogin->user_id,
                    'status' => $request->status_verifikasi,
                    'catatan_admin' => $request->catatan_admin,
                    'link_drive_admin' => $request->link_drive_admin,
                    'tanggal_verifikasi' => now(),
                ]);
            }

            $organisasi->load(['verifikasi.admin']);

            DB::commit();

            $statusMessage = $request->status_verifikasi === 'aktif' ? 'disetujui' : 'ditolak';

            return response()->json([
                'success' => true,
                'message' => "Organisasi berhasil {$statusMessage}",
                'data' => $organisasi
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate verifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // FIX: Method getByPengguna - ini adalah perbaikan untuk error "by-user"
    public function getByPengguna(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            if (!$currentLogin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau telah kedaluwarsa'
                ], 401);
            }
            
            $organisasi = Organisasi::with(['kajian', 'sosialMedias', 'strukturPengurus', 'verifikasi.admin'])
                ->where('pengguna_id', $currentLogin->user_id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data organisasi pengguna berhasil diambil',
                'data' => $organisasi,
                'count' => $organisasi->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // FIX: Method getPendingVerification - ini adalah perbaikan untuk error "pending-verification"
    public function getPendingVerification(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            if (!$currentLogin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau telah kedaluwarsa'
                ], 401);
            }
            
            // Cek apakah user adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak'
                ], 403);
            }

            $organisasi = Organisasi::with([
                'pengguna' => fn($q) => $q->select('id', 'nama_pengguna', 'email_pengguna', 'no_telpon_pengguna'), 
                'kajian' => fn($q) => $q->select('id', 'nama_kajian'), 
                'verifikasi'
                ])
                ->where('status_verifikasi', Organisasi::STATUS_PENDING)
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data organisasi yang menunggu verifikasi',
                'data' => $organisasi,
                'count' => $organisasi->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Method untuk mengecek ketersediaan nama organisasi
    public function checkNameAvailability(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            if (!$currentLogin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Ambil nama pengguna
            $pengguna = Pengguna::find($currentLogin->user_id);
            $namaOrganisasi = $pengguna->nama_pengguna;
            
            // Cek apakah nama sudah digunakan
            $isAvailable = !Organisasi::where('nama_organisasi', $namaOrganisasi)->exists();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'nama_organisasi' => $namaOrganisasi,
                    'is_available' => $isAvailable,
                    'message' => $isAvailable 
                        ? 'Nama organisasi tersedia'
                        : 'Nama organisasi sudah terdaftar dalam sistem'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek ketersediaan nama',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getCurrentLogin(Request $request)
    {
        $bearerToken = $request->bearerToken();
        
        if (!$bearerToken) {
            return null;
        }

        return Login::where('bearer_token', $bearerToken)
                   ->where('is_active', true)
                   ->where('expires_at', '>', now())
                   ->first();
    }
}