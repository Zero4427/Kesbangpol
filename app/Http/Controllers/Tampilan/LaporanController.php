<?php

namespace App\Http\Controllers\Tampilan;

use App\Http\Controllers\Controller;
use App\Models\Akses\Admin;
use App\Models\Tampilan\Laporan;
use App\Models\Akses\Organisasi;
use App\Models\Status\Verifikasi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LaporanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Laporan::query()->with('organisasi');
        
        // Filter berdasarkan tahun jika ada
        if ($request->has('tahun')) {
            $query->where('tahun', $request->tahun);
        }
        
        // Filter berdasarkan status jika ada
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan organisasi_id jika ada
        if ($request->has('organisasi_id')) {
            $query->where('organisasi_id', $request->organisasi_id);
        }

        $laporans = $query->orderBy('tahun', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'message' => 'Data laporan berhasil diambil',
            'data' => $laporans
        ]);
    }

    public function show($id): JsonResponse
    {
        $laporan = Laporan::with('organisasi')->find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Data laporan berhasil diambil',
            'data' => $laporan
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organisasi_id' => 'required|exists:organisasis,id',
            'file_laporan' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'tahun' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'judul' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'status' => 'nullable|in:draft,submitted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah organisasi aktif
        $verifikasi = Verifikasi::where('organisasi_id', $request->organisasi_id)
            ->where('status', 'aktif')
            ->first();

        if (!$verifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Organisasi belum terverifikasi atau tidak aktif'
            ], 403);
        }

        // Cek apakah pengguna memiliki akses ke organisasi
        $organisasi = Organisasi::find($request->organisasi_id);
        if ($request->user()->id !== $organisasi->pengguna_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membuat laporan pada organisasi ini'
            ], 403);
        }

        // Cek apakah sudah ada laporan untuk tahun yang sama
        $existingLaporan = Laporan::where('organisasi_id', $request->organisasi_id)
            ->where('tahun', $request->tahun)
            ->first();

        if ($existingLaporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan untuk tahun ini sudah ada'
            ], 422);
        }

        try {
            // Upload file
            $file = $request->file('file_laporan');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('laporans', $filename, 'public');

            // Simpan ke database
            $laporan = Laporan::create([
                'organisasi_id' => $request->organisasi_id,
                'file_laporan' => $path,
                'tahun' => $request->tahun,
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'status' => $request->status ?? 'draft'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dibuat',
                'data' => $laporan
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $laporan = Laporan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan'
            ], 404);
        }

        // Hanya pengguna yang memiliki organisasi atau admin yang bisa update
        $organisasi = Organisasi::find($laporan->organisasi_id);
        if ($request->user()->id !== $organisasi->pengguna_id && !$request->user() instanceof \App\Models\Akses\Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengupdate laporan ini'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file_laporan' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'tahun' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'judul' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'status' => 'nullable|in:draft,submitted,archived'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah sudah ada laporan lain untuk tahun yang sama
        if ($request->tahun !== $laporan->tahun) {
            $existingLaporan = Laporan::where('organisasi_id', $laporan->organisasi_id)
                ->where('tahun', $request->tahun)
                ->where('id', '!=', $id)
                ->first();

            if ($existingLaporan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Laporan untuk tahun ini sudah ada'
                ], 422);
            }
        }

        try {
            $updateData = [
                'tahun' => $request->tahun,
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'status' => $request->status ?? $laporan->status
            ];

            // Hanya admin yang bisa mengubah status ke archived
            if ($request->status === 'archived' && !$request->user() instanceof \App\Models\Akses\Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya admin yang dapat mengarsipkan laporan'
                ], 403);
            }

            // Upload file baru jika ada
            if ($request->hasFile('file_laporan')) {
                // Hapus file lama
                if (Storage::disk('public')->exists($laporan->file_laporan)) {
                    Storage::disk('public')->delete($laporan->file_laporan);
                }

                // Upload file baru
                $file = $request->file('file_laporan');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('laporans', $filename, 'public');
                $updateData['file_laporan'] = $path;
            }

            $laporan->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil diupdate',
                'data' => $laporan->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $laporan = Laporan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan'
            ], 404);
        }

        // Hanya pengguna yang memiliki organisasi atau admin yang bisa menghapus
        $organisasi = Organisasi::find($laporan->organisasi_id);
        if ($request->user()->id !== $organisasi->pengguna_id && !$request->user() instanceof Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus laporan ini'
            ], 403);
        }

        try {
            // Hapus file dari storage
            if (Storage::disk('public')->exists($laporan->file_laporan)) {
                Storage::disk('public')->delete($laporan->file_laporan);
            }

            $laporan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function archive($id, Request $request): JsonResponse
    {
        $laporan = Laporan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan'
            ], 404);
        }

        // Hanya admin yang bisa mengarsipkan
        if (!$request->user() instanceof \App\Models\Akses\Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat mengarsipkan laporan'
            ], 403);
        }

        try {
            $laporan->update(['status' => 'archived']);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil diarsipkan',
                'data' => $laporan->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengarsipkan laporan: ' . $e->getMessage()
            ], 500);
        }
    }
}