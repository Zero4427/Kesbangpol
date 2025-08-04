<?php

namespace App\Http\Controllers\Tampilan;

use App\Http\Controllers\Controller;
use App\Models\Tampilan\Kajian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KajianController extends Controller
{
    public function index()
    {
        $kajians = Kajian::all();
        return response()->json([
            'status' => 'success',
            'data' => $kajians
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kajian' => 'required|string|max:255',
            'deskripsi_kajian' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $kajian = Kajian::create($request->only(['nama_kajian', 'deskripsi_kajian']));

        return response()->json([
            'status' => 'success',
            'message' => 'Kajian berhasil dibuat',
            'data' => $kajian
        ], 201);
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
            'nama_kajian' => 'required|string|max:255',
            'deskripsi_kajian' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $kajian->update($request->only(['nama_kajian', 'deskripsi_kajian']));

        return response()->json([
            'status' => 'success',
            'message' => 'Kajian berhasil diperbarui',
            'data' => $kajian
        ], 200);
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

        $kajian->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kajian berhasil dihapus'
        ], 200);
    }
}