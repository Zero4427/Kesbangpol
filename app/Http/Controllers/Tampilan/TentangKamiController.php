<?php

namespace App\Http\Controllers\Tampilan;

use App\Http\Controllers\Controller;
use App\Models\Tampilan\TentangKami;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TentangKamiController extends Controller
{
    public function index()
    {
        try {
            $tentangKami = TentangKami::latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Data Tentang Kami berhasil diambil',
                'data' => $tentangKami
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
            'deskripsi' => 'required|string',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gambarPath = null;
            if ($request->hasFile('gambar')) {
                $gambarPath = $request->file('gambar')->store('tentang-kami', 'public');
            }

            $tentangKami = TentangKami::create([
                'deskripsi' => $request->deskripsi,
                'gambar' => $gambarPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data Tentang Kami berhasil dibuat',
                'data' => $tentangKami
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
            $tentangKami = TentangKami::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data Tentang Kami berhasil diambil',
                'data' => $tentangKami
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'deskripsi' => 'sometimes|string',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tentangKami = TentangKami::findOrFail($id);

            if ($request->hasFile('gambar')) {
                if ($tentangKami->gambar) {
                    Storage::disk('public')->delete($tentangKami->gambar);
                }
                $tentangKami->gambar = $request->file('gambar')->store('tentang-kami', 'public');
            }

            $tentangKami->update($request->except(['gambar']));

            return response()->json([
                'success' => true,
                'message' => 'Data Tentang Kami berhasil diupdate',
                'data' => $tentangKami
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $tentangKami = TentangKami::findOrFail($id);

            if ($tentangKami->gambar) {
                Storage::disk('public')->delete($tentangKami->gambar);
            }

            $tentangKami->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data Tentang Kami berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}