<?php

namespace App\Http\Controllers\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\Verifikasi;
use App\Models\Akses\Organisasi;
use App\Models\Akses\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifikasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            // Cek apakah user adalah admin
            if ($currentLogin->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat melihat data verifikasi'
                ], 403);
            }

            $query = Verifikasi::with([
                'organisasi' => fn($q) => $q->select('id', 'nama_organisasi', 'pengguna_id'),
                'organisasi.pengguna' => fn($q) => $q->select('id', 'nama_pengguna'),
                'admin' => fn($q) => $q->select('id', 'nama_admin')
            ]);

            if ($request->has('status')) {
                $status = $request->input('status');
                if ($status === 'proses') {
                    $query->pending();
                } elseif ($status === 'aktif') {
                    $query->approved();
                } elseif ($status === 'tidak aktif') {
                    $query->rejected();
                }
            }

            $verifikasis = $query->latest('created_at')->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Data verifikasi berhasil diambil',
                'data' => $verifikasis
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            $verifikasi = Verifikasi::with([
                    'organisasi' => fn($q) => $q->select('id', 'nama_organisasi', 'pengguna_id', 'kajian_id'),
                    'organisasi.pengguna' => fn($q) => $q->select('id', 'nama_pengguna'),
                    'organisasi.kajian' => fn($q) => $q->select('id', 'nama_kajian'),
                    'admin' => fn($q) => $q->select('id', 'nama_admin')
                ])
                ->findOrFail($id);
            
            // Cek permission
            if ($currentLogin->user_type === 'pengguna') {
                // Pengguna hanya bisa lihat verifikasi organisasi milik sendiri
                if ($verifikasi->organisasi->pengguna_id !== $currentLogin->user_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akses ditolak'
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Data verifikasi berhasil diambil',
                'data' => $verifikasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verifikasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $currentLogin = $this->getCurrentLogin($request);
        
        // Cek apakah user adalah admin
        if ($currentLogin->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya admin yang dapat melakukan verifikasi'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:'.Verifikasi::STATUS_PENDING.','.Verifikasi::STATUS_APPROVED.','.Verifikasi::STATUS_REJECTED,
            'catatan_admin' => 'nullable|string|max:1000',
            'link_drive_admin' => 'nullable|url|max:255',
        ]);

        try {
            DB::beginTransaction();

            $verifikasi = Verifikasi::with('organisasi')->findOrFail($id);
            
            // Update verifikasi
            $verifikasi->setStatus(
                $request->status,
                $currentLogin->user_id,
                $request->catatan_admin,
                $request->link_drive_admin
            );

            // Update status organisasi sesuai dengan verifikasi
            $organisasiStatus = match($request->status) {
                Verifikasi::STATUS_APPROVED => Organisasi::STATUS_VERIFIED,
                Verifikasi::STATUS_REJECTED => Organisasi::STATUS_REJECTED,
                default => Organisasi::STATUS_PENDING
            };

            $verifikasi->organisasi->update(['status_verifikasi' => $organisasiStatus]);

            $verifikasi->load([
                'organisasi' => fn($q) => $q->select('id', 'nama_organisasi', 'pengguna_id'),
                'organisasi.pengguna' => fn($q) => $q->select('id', 'nama_pengguna'),
                'admin' => fn($q) => $q->select('id', 'nama_admin')
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status verifikasi berhasil diperbarui',
                'data' => $verifikasi
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui verifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStats(Request $request)
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

            $stats = [
                'total' => Verifikasi::count(),
                'pending' => Verifikasi::pending()->count(),
                'approved' => Verifikasi::approved()->count(),
                'rejected' => Verifikasi::rejected()->count(),
                'today' => Verifikasi::whereDate('created_at', today())->count(),
                'this_week' => Verifikasi::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Statistik verifikasi berhasil diambil',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper method untuk mendapatkan login session saat ini
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