<?php

use Illuminate\Support\Facades\Route;

// ===== Import Controller =====
use App\Http\Controllers\Akses\AdminController;
use App\Http\Controllers\Akses\AuthController;
use App\Http\Controllers\Akses\OrganisasiController;
use App\Http\Controllers\Akses\ImprovedPasswordResetController;
use App\Http\Controllers\Tampilan\LaporanController;
use App\Http\Controllers\Dokumentasi\BeritaController;
use App\Http\Controllers\Tampilan\BidangController;
use App\Http\Controllers\Tampilan\FaqController;
use App\Http\Controllers\Tampilan\TentangKamiController;
use App\Http\Controllers\Tampilan\KajianController;
use App\Http\Controllers\Status\VerifikasiController;

// ===== AUTH ROUTES (public) =====
Route::post('/auth/register-pengguna', [AuthController::class, 'registerPengguna']);
Route::post('/auth/login-pengguna', [AuthController::class, 'loginPengguna']);
Route::post('/auth/login-admin', [AuthController::class, 'loginAdmin']);

// ===== ORGANISASI ROUTES (PUBLIC) =====
// Route untuk melihat organisasi tanpa bearer token (hanya yang terverifikasi)
Route::get('/organisasi/public', [OrganisasiController::class, 'indexPublic']);
Route::get('/organisasi/public/{id}', [OrganisasiController::class, 'showPublic']);

// ===== KAJIAN ROUTES (PUBLIC) =====
// Route untuk melihat kajian tanpa bearer token
Route::get('/kajian', [KajianController::class, 'index']);
Route::get('/kajian/{id}', [KajianController::class, 'show']);

// ===== BIDANG ROUTES (PUBLIC) =====
// Route untuk melihat bidang tanpa bearer token
Route::get('/bidang', [BidangController::class, 'index']);
Route::get('/bidang/{id}', [BidangController::class, 'show']);

// ===== TENTANG KAMI ROUTES (PUBLIC) =====
Route::get('/tentang-kami', [TentangKamiController::class, 'index']);
Route::get('/tentang-kami/{id}', [TentangKamiController::class, 'show']);

// ===== FAQ (PUBLIC) =====
Route::get('/faq', [FaqController::class, 'index']);
Route::get('/faq/{id}', [FaqController::class, 'show']);

// ===== BERITA (PUBLIC) =====
Route::get('/berita/all', [BeritaController::class, 'index']);
Route::get('/berita/all/{id}', [BeritaController::class, 'show']);

// ===== AUTH ROUTES (protected) =====
Route::middleware(['auth.bearer'])->group(function () {
    Route::post('/auth/register-admin', [AuthController::class, 'registerAdmin']); // hanya super admin
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('/auth/validate-token', [AuthController::class, 'validateToken']);
});

// ===== ADMIN ROUTES =====
Route::middleware(['auth.bearer'])->group(function () {
    // CRUD Admin (hanya super admin)
    Route::post('/admin/create', [AdminController::class, 'createAdmin']);
    Route::get('/admin/all', [AdminController::class, 'getAllAdmins']);
    Route::put('/admin/{id}/update', [AdminController::class, 'updateAdmin']);
    Route::patch('/admin/{id}/status', [AdminController::class, 'updateAdminStatus']);
    Route::delete('/admin/{id}', [AdminController::class, 'deleteAdmin']);

    // User data (admin dan super admin)
    Route::get('/admin/users', [AdminController::class, 'getAllUsers']);

    // Login session management
    Route::get('/admin/sessions', [AdminController::class, 'getLoginSessions']);
    Route::patch('/admin/session/{id}/terminate', [AdminController::class, 'terminateSession']);
    Route::patch('/admin/user/{userId}/{userType}/terminate-all', [AdminController::class, 'terminateAllUserSessions']);
    Route::patch('/admin/sessions/cleanup-expired', [AdminController::class, 'cleanupExpiredSessions']);

    // System statistics
    Route::get('/admin/system-stats', [AdminController::class, 'getSystemStats']);
});

// ===== ADMIN ORGANISASI MANAGEMENT ROUTES (NEW) =====
Route::middleware(['auth.bearer', 'admin.only'])->group(function () {
    // Admin dapat melihat semua organisasi dengan berbagai filter
    Route::get('/admin/organisasi/all', [OrganisasiController::class, 'getAllOrganisasiForAdmin']);
    
    // Admin dapat melihat organisasi berdasarkan status tertentu
    Route::get('/admin/organisasi/status/{status}', [OrganisasiController::class, 'getOrganisasiByStatus']);
    
    // Admin dapat melihat detail lengkap organisasi (termasuk yang belum diverifikasi)
    Route::get('/admin/organisasi/{id}/detail', [OrganisasiController::class, 'getDetailForAdmin']);
    
    // Admin mendapatkan statistik organisasi
    Route::get('/admin/organisasi/statistics', [OrganisasiController::class, 'getOrganisasiStatistics']);
    
    // Route untuk admin mendapatkan organisasi yang menunggu verifikasi
    Route::get('/admin/organisasi/pending-verification', [OrganisasiController::class, 'getPendingVerification']);
    
    // Route untuk admin melakukan verifikasi organisasi
    Route::post('/admin/organisasi/{id}/verifikasi', [OrganisasiController::class, 'updateVerifikasi']);
});

// ===== ORGANISASI ROUTES (PROTECTED) =====
Route::middleware(['auth.bearer'])->group(function () {
    // CRUD organisasi
    Route::get('/organisasi', [OrganisasiController::class, 'index']);
    Route::post('/organisasi', [OrganisasiController::class, 'store']);
    Route::get('/organisasi/{id}', [OrganisasiController::class, 'show']);
    Route::put('/organisasi/{id}', [OrganisasiController::class, 'update']);
    Route::delete('/organisasi/{id}', [OrganisasiController::class, 'destroy']);

    // Ambil organisasi berdasarkan pengguna login - FIX untuk error "by-user"
    Route::get('/organisasi/user/my-organizations', [OrganisasiController::class, 'getByPengguna']);
    
    // Route untuk mengecek ketersediaan nama organisasi berdasarkan nama pengguna
    Route::get('/organisasi/user/check-name-availability', [OrganisasiController::class, 'checkNameAvailability']);
});

// ===== BERITA ROUTES =====
Route::middleware(['auth.bearer'])->group(function () {
    Route::get('/berita', [BeritaController::class, 'index']);
    Route::post('/berita', [BeritaController::class, 'store']);
    Route::get('/berita/{id}', [BeritaController::class, 'show']);
    Route::put('/berita/{id}/update', [BeritaController::class, 'update']);
    Route::delete('/berita/{id}', [BeritaController::class, 'destroy']);
    Route::post('/berita/{id}/approval', [BeritaController::class, 'updateApproval']);

    Route::get('/berita/by-user', [BeritaController::class, 'getByPengguna']);
    Route::get('/berita/by-organisasi/{organisasi_id}', [BeritaController::class, 'getByOrganisasi']);
});

// ===== BIDANG ROUTES (PROTECTED - ADMIN ONLY) =====
Route::middleware(['auth.bearer', 'admin.only'])->group(function () {
    Route::get('/admin/bidang', [BidangController::class, 'index']);
    Route::get('/admin/bidang/{id}', [BidangController::class, 'show']);
    Route::post('/admin/bidang', [BidangController::class, 'store']);
    Route::put('/admin/bidang/{id}/update', [BidangController::class, 'update']);
    Route::delete('/admin/bidang/{id}', [BidangController::class, 'destroy']);
});

// ===== FAQ ROUTES =====
Route::middleware(['auth.bearer'])->group(function () {
    Route::get('/admin/faq', [FaqController::class, 'index']);
    Route::get('/admin/faq/{id}', [FaqController::class, 'show']);
    Route::post('/admin/faq', [FaqController::class, 'store'])->middleware('admin.only');
    Route::put('/admin/faq/{id}/update', [FaqController::class, 'update'])->middleware('admin.only');
    Route::delete('/admin/faq/{id}', [FaqController::class, 'destroy'])->middleware('admin.only');
});

// ===== TENTANG KAMI ROUTES (PROTECTED - ADMIN ONLY) =====
Route::middleware(['auth.bearer', 'admin.only'])->group(function () {
    Route::get('/admin/tentang-kami', [TentangKamiController::class, 'index']);
    Route::get('/admin/tentang-kami/{id}', [TentangKamiController::class, 'show']);
    Route::post('/admin/tentang-kami', [TentangKamiController::class, 'store']);
    Route::put('/admin/tentang-kami/{id}/update', [TentangKamiController::class, 'update']);
    Route::delete('/admin/tentang-kami/{id}', [TentangKamiController::class, 'destroy']);
});

// ===== VERIFIKASI STATUS (KHUSUS ADMIN) =====
Route::middleware(['auth.bearer', 'admin.only'])->group(function () {
    Route::get('/verifikasi', [VerifikasiController::class, 'index']);
    Route::get('/verifikasi/{id}', [VerifikasiController::class, 'show']);
    Route::put('/verifikasi/{id}/update', [VerifikasiController::class, 'update']);
    Route::get('/verifikasi/stats', [VerifikasiController::class, 'getStats']);
});

// ===== KAJIAN ROUTES (PROTECTED - ADMIN ONLY) =====
Route::middleware(['auth.bearer'])->group(function () {
    Route::get('/admin/kajian', [KajianController::class, 'index'])->middleware('admin.only');
    Route::get('/admin/kajian/{id}', [KajianController::class, 'show'])->middleware('admin.only');
    Route::post('/admin/kajian', [KajianController::class, 'store'])->middleware('admin.only');
    Route::put('/admin/kajian/{id}/update', [KajianController::class, 'update'])->middleware('admin.only');
    Route::delete('/admin/kajian/{id}', [KajianController::class, 'destroy'])->middleware('admin.only');
});

// === LAPORAN ROUTES ===
Route::middleware(['auth.bearer'])->group(function () {
    Route::get('/laporans', [LaporanController::class, 'index']);
    Route::post('/laporans', [LaporanController::class, 'store']);
    Route::get('/laporans/{id}', [LaporanController::class, 'show']);
    Route::put('/laporans/{id}', [LaporanController::class, 'update']);
    Route::delete('/laporans/{id}', [LaporanController::class, 'destroy']);
    Route::patch('/laporans/{id}/archive', [LaporanController::class, 'archive'])->middleware('admin.only');
});

// ===== PASSWORD RESET ROUTES =====
Route::middleware(['throttle:otp'])->group(function () {
    Route::post('/auth/forgot-password/request-otp', [ImprovedPasswordResetController::class, 'requestOtp']);
    Route::post('/auth/forgot-password/resend-otp', [ImprovedPasswordResetController::class, 'resendOtp']);
});

Route::post('/auth/forgot-password/verify-otp', [ImprovedPasswordResetController::class, 'verifyOtp']);
Route::post('/auth/forgot-password/reset-password', [ImprovedPasswordResetController::class, 'resetPassword']);
Route::get('/auth/forgot-password/otp-status', [ImprovedPasswordResetController::class, 'getOtpStatus']);
Route::post('/auth/forgot-password/otp-status', [ImprovedPasswordResetController::class, 'getOtpStatus']);

Route::middleware(['auth.bearer', 'admin.only'])->group(function () {
    Route::get('/admin/password-reset/statistics', [ImprovedPasswordResetController::class, 'getOtpStatistics']);
    Route::post('/admin/password-reset/cleanup-expired', [ImprovedPasswordResetController::class, 'cleanupExpiredOtp']);
});