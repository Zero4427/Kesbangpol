<?php
// app/Http/Controllers/BeritaController.php
namespace App\Http\Controllers\Dokumentasi;

use App\Http\Controllers\Controller;
use App\Models\Dokumentasi\Berita;
use App\Models\Akses\Organisasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Berita::with(['pengguna', 'organisasi']);

            // Filter for approved news only for regular users
            if (!$request->user() instanceof \App\Models\Akses\Admin) {
                $query->where('is_approved', true);
            }

            $berita = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data berita berhasil diambil',
                'data' => $berita
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
            'nama_kegiatan' => 'required|string|max:255',
            'lokasi_kegiatan' => 'required|string|max:255',
            'tanggal_kegiatan' => 'required|date',
            'deskripsi_kegiatan' => 'required|string',
            'dokumentasi_kegiatan.' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user owns the organisation
            //$organisasi = \App\Models\Akses\Organisasi::findOrFail($request->organisasi_id);
            $organisasi = Organisasi::where('pengguna_id', $request->user()->id)->first();
            if ($organisasi->pengguna_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - You can only create news for your own organization'
                ], 403);
            }

            $dokumentasiPaths = [];
            if ($request->hasFile('dokumentasi_kegiatan')) {
                foreach ($request->file('dokumentasi_kegiatan') as $file) {
                    $dokumentasiPaths[] = $file->store('dokumentasi', 'public');
                }
            }

            $berita = Berita::create([
                'pengguna_id' => $request->user()->id,
                //'organisasi_id' => $request->organisasi_id,
                'organisasi_id' => $organisasi->id,
                'nama_kegiatan' => $request->nama_kegiatan,
                'lokasi_kegiatan' => $request->lokasi_kegiatan,
                'tanggal_kegiatan' => $request->tanggal_kegiatan,
                'deskripsi_kegiatan' => $request->deskripsi_kegiatan,
                'dokumentasi_kegiatan' => $dokumentasiPaths,
            ]);

            $berita->load(['pengguna', 'organisasi']);

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil dibuat',
                'data' => $berita
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $berita = Berita::with(['pengguna', 'organisasi'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data berita berhasil diambil',
                'data' => $berita
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $berita = Berita::findOrFail($id);

            // Check if user owns this berita
            if ($request->user()->id !== $berita->pengguna_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'organisasi_id' => 'sometimes|exists:organisasis,id',
                'nama_kegiatan' => 'sometimes|string|max:255',
                'lokasi_kegiatan' => 'sometimes|string|max:255',
                'tanggal_kegiatan' => 'sometimes|date',
                'deskripsi_kegiatan' => 'sometimes|string',
                'dokumentasi_kegiatan' => 'nullable|array',
                'dokumentasi_kegiatan.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle documentation upload
            if ($request->hasFile('dokumentasi_kegiatan')) {
                // Delete old files
                if ($berita->dokumentasi_kegiatan) {
                    foreach ($berita->dokumentasi_kegiatan as $oldFile) {
                        Storage::disk('public')->delete($oldFile);
                    }
                }

                $dokumentasiPaths = [];
                foreach ($request->file('dokumentasi_kegiatan') as $file) {
                    $dokumentasiPaths[] = $file->store('dokumentasi', 'public');
                }
                $berita->dokumentasi_kegiatan = $dokumentasiPaths;
            }

            $berita->update($request->except(['dokumentasi_kegiatan']));

            $berita->load(['pengguna', 'organisasi']);

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil diupdate',
                'data' => $berita
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $berita = Berita::findOrFail($id);

            // Check if user owns this berita or is admin
            if ($request->user()->id !== $berita->pengguna_id && !$request->user() instanceof \App\Models\Akses\Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Delete associated files
            if ($berita->dokumentasi_kegiatan) {
                foreach ($berita->dokumentasi_kegiatan as $file) {
                    Storage::disk('public')->delete($file);
                }
            }

            $berita->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateApproval(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_approved' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $berita = Berita::findOrFail($id);
            $berita->update(['is_approved' => $request->is_approved]);

            return response()->json([
                'success' => true,
                'message' => 'Status approval berhasil diupdate',
                'data' => $berita
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByPengguna(Request $request)
    {
        try {
            $berita = Berita::with(['organisasi'])
                ->where('pengguna_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data berita pengguna berhasil diambil',
                'data' => $berita
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByOrganisasi($organisasi_id)
    {
        try {
            $berita = Berita::with(['pengguna'])
                ->where('organisasi_id', $organisasi_id)
                ->where('is_approved', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data berita organisasi berhasil diambil',
                'data' => $berita
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}