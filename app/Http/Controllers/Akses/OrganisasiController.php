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
                if (in_array($status, [Verifikasi::STATUS_PENDING, Verifikasi::STATUS_APPROVED, Verifikasi::STATUS_REJECTED])) {
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
            $organisasi = Organisasi::findOrFail($id);
            $currentLogin = $this->getCurrentLogin($request);

            // Check if user owns this organisasi or is admin
            if ($currentLogin->user_type === 'pengguna' && $currentLogin->user_id !== $organisasi->pengguna_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'nama_kajian' => 'sometimes|string|exists:kajians,nama_kajian', // Menggunakan nama kajian
                'tanggal_berdiri' => 'sometimes|date',
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
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle logo upload
            if ($request->hasFile('logo_organisasi')) {
                if ($organisasi->logo_organisasi) {
                    Storage::disk('public')->delete($organisasi->logo_organisasi);
                }
                $organisasi->logo_organisasi = $request->file('logo_organisasi')->store('logos', 'public');
            }

            // Handle file upload
            if ($request->hasFile('file_persyaratan')) {
                if ($organisasi->file_persyaratan) {
                    Storage::disk('public')->delete($organisasi->file_persyaratan);
                }
                $organisasi->file_persyaratan = $request->file('file_persyaratan')->store('persyaratan', 'public');
            }

            // Update kajian jika ada perubahan nama kajian
            $updateData = $request->except(['logo_organisasi', 'file_persyaratan', 'sosial_media', 'struktur_pengurus', 'nama_kajian']);
            
            if ($request->has('nama_kajian')) {
                $kajian = Kajian::where('nama_kajian', $request->nama_kajian)->first();
                $updateData['kajian_id'] = $kajian->id;
            }

            $organisasi->update($updateData);

            // Update sosial media
            if ($request->has('sosial_media')) {
                $organisasi->sosialMedias()->delete();
                foreach ($request->sosial_media as $sosmed) {
                    SosialMedia::create([
                        'organisasi_id' => $organisasi->id,
                        'platform' => $sosmed['platform'],
                        'url' => $sosmed['url'],
                    ]);
                }
            }

            // Update struktur pengurus
            if ($request->has('struktur_pengurus')) {
                $organisasi->strukturPengurus()->delete();
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
            }

            $organisasi->load(['pengguna', 'kajian', 'sosialMedias', 'strukturPengurus']);

            return response()->json([
                'success' => true,
                'message' => 'Organisasi berhasil diupdate',
                'data' => $organisasi
            ]);

        } catch (\Exception $e) {
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

    public function getByPengguna(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            $organisasi = Organisasi::with(['kajian', 'sosialMedias', 'strukturPengurus', 'verifikasi.admin'])
                ->where('pengguna_id', $currentLogin->user_id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data organisasi pengguna berhasil diambil',
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

    public function getPendingVerification(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            // Cek apakah user adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak'
                ], 403);
            }

            $organisasi = Organisasi::with([
                'pengguna' => fn($q) => $q->select('id', 'nama_pengguna'), 
                'kajian' => fn($q) => $q->select('id', 'nama_kajian'), 
                'verifikasi'
                ])
                ->pending()
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