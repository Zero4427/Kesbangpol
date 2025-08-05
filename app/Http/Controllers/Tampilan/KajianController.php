<?php

namespace App\Http\Controllers\Tampilan;

use App\Http\Controllers\Controller;
use App\Models\Tampilan\Kajian;
use App\Models\Akses\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KajianController extends Controller
{
    // Method untuk public dan protected (tidak berubah, tetap bisa diakses tanpa bearer token)
    public function index()
    {
        $kajians = Kajian::all();
        return response()->json([
            'status' => 'success',
            'data' => $kajians
        ], 200);
    }

    public function show($id)
    {
        $kajian = Kajian::find($id);

        if (!$kajian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kajian tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $kajian
        ], 200);
    }

    // Method khusus admin (memerlukan bearer token dan middleware admin.only)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kajian' => 'required|string|max:255|unique:kajians,nama_kajian',
            'deskripsi_kajian' => 'nullable|string',
        ], [
            'nama_kajian.unique' => 'Nama kajian sudah terdaftar dalam sistem.',
            'nama_kajian.required' => 'Nama kajian harus diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $kajian = Kajian::create($request->only(['nama_kajian', 'deskripsi_kajian']));

            return response()->json([
                'status' => 'success',
                'message' => 'Kajian berhasil dibuat',
                'data' => $kajian
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan kajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $kajian = Kajian::find($id);

        if (!$kajian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kajian tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_kajian' => 'required|string|max:255|unique:kajians,nama_kajian,' . $id,
            'deskripsi_kajian' => 'nullable|string',
        ], [
            'nama_kajian.unique' => 'Nama kajian sudah terdaftar dalam sistem.',
            'nama_kajian.required' => 'Nama kajian harus diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $kajian->update($request->only(['nama_kajian', 'deskripsi_kajian']));

            return response()->json([
                'status' => 'success',
                'message' => 'Kajian berhasil diperbarui',
                'data' => $kajian
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui kajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $kajian = Kajian::find($id);

        if (!$kajian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kajian tidak ditemukan'
            ], 404);
        }

        try {
            // Cek apakah kajian masih digunakan oleh organisasi
            if ($kajian->organisasi()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kajian tidak dapat dihapus karena masih digunakan oleh organisasi'
                ], 422);
            }

            $kajian->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Kajian berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus kajian',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}