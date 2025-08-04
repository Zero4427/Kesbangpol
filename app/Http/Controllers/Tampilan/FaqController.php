<?php

namespace App\Http\Controllers\Tampilan;

use App\Http\Controllers\Controller;
use App\Models\Tampilan\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    public function index()
    {
        try {
            $faqs = Faq::where('is_active', true)->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data FAQ berhasil diambil',
                'data' => $faqs
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
            'pertanyaan' => 'required|string',
            'jawaban' => 'required|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $faq = Faq::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'FAQ berhasil dibuat',
                'data' => $faq
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
            $faq = Faq::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data FAQ berhasil diambil',
                'data' => $faq
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'pertanyaan' => 'sometimes|string',
            'jawaban' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $faq = Faq::findOrFail($id);
            $faq->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'FAQ berhasil diupdate',
                'data' => $faq
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
            $faq = Faq::findOrFail($id);
            $faq->delete();

            return response()->json([
                'success' => true,
                'message' => 'FAQ berhasil dihapus'
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