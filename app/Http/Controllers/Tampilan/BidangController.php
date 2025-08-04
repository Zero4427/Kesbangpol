<?php

namespace App\Http\Controllers\Tampilan;

use App\Http\Controllers\Controller;
use App\Models\Tampilan\Bidang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class BidangController extends Controller
{
    public function index()
    {
        try {
            $bidang = Bidang::orderBy('nama_bidang', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data bidang berhasil diambil',
                'data' => $bidang
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
            'nama_bidang' => 'required|string|max:255',
            'deskripsi_bidang' => 'nullable|string',
            'gambar_karyawan' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jumlah_staf' => 'required|integer|min:0',
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
            if ($request->hasFile('gambar_karyawan')) {
                $gambarPath = $request->file('gambar_karyawan')->store('bidang', 'public');
            }

            $bidang = Bidang::create([
                'nama_bidang' => $request->nama_bidang,
                'deskripsi_bidang' => $request->deskripsi_bidang,
                'gambar_karyawan' => $gambarPath,
                'jumlah_staf' => $request->jumlah_staf,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bidang berhasil dibuat',
                'data' => $bidang
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
            $bidang = Bidang::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data bidang berhasil diambil',
                'data' => $bidang
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_bidang' => 'sometimes|string|max:255',
            'deskripsi_bidang' => 'nullable|string',
            'gambar_karyawan' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jumlah_staf' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bidang = Bidang::findOrFail($id);

            if ($request->hasFile('gambar_karyawan')) {
                if ($bidang->gambar_karyawan) {
                    Storage::disk('public')->delete($bidang->gambar_karyawan);
                }
                $bidang->gambar_karyawan = $request->file('gambar_karyawan')->store('bidang', 'public');
            }

            $bidang->update($request->except(['gambar_karyawan']));

            return response()->json([
                'success' => true,
                'message' => 'Bidang berhasil diupdate',
                'data' => $bidang
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
            $bidang = Bidang::findOrFail($id);

            if ($bidang->gambar_karyawan) {
                Storage::disk('public')->delete($bidang->gambar_karyawan);
            }

            $bidang->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bidang berhasil dihapus'
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
