<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Akses\Admin;
use App\Models\Akses\Pengguna;
use App\Models\Akses\PasswordResetOtp;
use App\Models\Akses\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    /**
     * Request OTP untuk reset password
     */
    private function sendOtpEmail($email, $otpCode, $user, $userType)
    {
        try {
            $name = $userType === 'admin' ? 
                ($user->nama_admin ?? 'Admin') : 
                ($user->nama_pengguna ?? 'User');
                
            $data = [
                'name' => $name,
                'otp_code' => $otpCode,
                'expires_in' => '10 minutes'
            ];

            // Log untuk debugging
            Log::info('Attempting to send OTP email', [
                'email' => $email,
                'name' => $name,
                'otp_partial' => substr($otpCode, 0, 2) . '****'
            ]);

            // Coba kirim email langsung dulu (tanpa queue)
            Mail::send('emails.otp', $data, function ($message) use ($email) {
                $message->to($email)
                        ->subject('Your OTP for Password Reset - INDOMAS');
            });

            Log::info('OTP email sent successfully', ['email' => $email]);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to send OTP email: ' . $e->getMessage());
        }
    }
    
    public function requestOtp(Request $request)
    {
        try {
            // Log raw request untuk debugging
            Log::info('OTP Request received', [
                'all_data' => $request->all(),
                'email' => $request->input('email'),
                'user_type' => $request->input('user_type'),
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type')
            ]);

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'user_type' => 'required|in:admin,pengguna'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;
            $userType = $request->user_type;

            Log::info('Validated request', ['email' => $email, 'user_type' => $userType]);

            // Cek apakah email ada di database
            $user = null;
            if ($userType === 'admin') {
                $user = Admin::where('email_admin', $email)->first();
                Log::info('Admin search result', ['found' => $user ? 'yes' : 'no']);
                
                if ($user && isset($user->is_active) && !$user->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Admin account is inactive'
                    ], 403);
                }
            } else {
                $user = Pengguna::where('email_pengguna', $email)->first();
                Log::info('Pengguna search result', ['found' => $user ? 'yes' : 'no']);
            }

            if (!$user) {
                Log::warning('User not found', ['email' => $email, 'user_type' => $userType]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found in our records'
                ], 404);
            }

            // Cek rate limiting
            $recentRequests = PasswordResetOtp::where('email', $email)
                ->where('user_type', $userType)
                ->where('created_at', '>', now()->subMinutes(15))
                ->count();

            Log::info('Rate limit check', ['recent_requests' => $recentRequests]);

            if ($recentRequests >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Please wait 15 minutes before trying again.',
                    'retry_after' => 15 * 60
                ], 429);
            }

            DB::beginTransaction();
            
            try {
                // Nonaktifkan OTP lama
                $deactivatedCount = PasswordResetOtp::where('email', $email)
                    ->where('user_type', $userType)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                Log::info('Deactivated old OTPs', ['count' => $deactivatedCount]);

                // Generate OTP baru
                $otpCode = $this->generateSecureOtpCode();
                
                // Simpan OTP ke database
                $passwordResetOtp = PasswordResetOtp::create([
                    'email' => $email,
                    'otp_code' => $otpCode,
                    'user_type' => $userType,
                    'expires_at' => now()->addMinutes(10),
                    'ip_address' => $request->ip()
                ]);

                Log::info('OTP created in database', ['id' => $passwordResetOtp->id]);

                // Kirim email OTP
                $this->sendOtpEmail($email, $otpCode, $user, $userType);

                DB::commit();

                Log::info('OTP process completed successfully');

                return response()->json([
                    'success' => true,
                    'message' => 'OTP has been sent to your email address',
                    'data' => [
                        'email' => $email,
                        'expires_at' => $passwordResetOtp->expires_at->toISOString(),
                        'expires_in_minutes' => 10
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                Log::error('Transaction failed', ['error' => $e->getMessage()]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('OTP Request Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'debug_info' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'otp_code' => 'required|string|size:6',
                'user_type' => 'required|in:admin,pengguna'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;
            $otpCode = $request->otp_code;
            $userType = $request->user_type;

            // Cari OTP yang valid
            $passwordResetOtp = PasswordResetOtp::where('email', $email)
                ->where('user_type', $userType)
                ->where('otp_code', $otpCode)
                ->where('is_active', true)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$passwordResetOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'email' => $email,
                    'user_type' => $userType,
                    'otp_token' => base64_encode($email . '|' . $otpCode . '|' . $userType),
                    'expires_at' => $passwordResetOtp->expires_at
                ]
            ]);

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password setelah OTP diverifikasi
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'otp_token' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Decode otp_token
            $tokenData = base64_decode($request->otp_token);
            $tokenParts = explode('|', $tokenData);
            
            if (count($tokenParts) !== 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 400);
            }

            [$email, $otpCode, $userType] = $tokenParts;

            // Verifikasi OTP sekali lagi
            $passwordResetOtp = PasswordResetOtp::where('email', $email)
                ->where('user_type', $userType)
                ->where('otp_code', $otpCode)
                ->where('is_active', true)
                ->where('is_used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$passwordResetOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP token'
                ], 400);
            }

            // Update password user
            DB::beginTransaction();
            
            try {
                if ($userType === 'admin') {
                    $user = Admin::where('email_admin', $email)->first();
                    if (!$user) {
                        throw new \Exception('Admin not found');
                    }
                    
                    $user->update([
                        'password_admin' => Hash::make($request->new_password)
                    ]);
                } else {
                    $user = Pengguna::where('email_pengguna', $email)->first();
                    if (!$user) {
                        throw new \Exception('Pengguna not found');
                    }
                    
                    $user->update([
                        'password_pengguna' => Hash::make($request->new_password)
                    ]);
                }

                // Tandai OTP sebagai sudah digunakan
                $passwordResetOtp->update([
                    'is_used' => true,
                    'is_active' => false
                ]);

                // Nonaktifkan semua session login aktif untuk user ini (force logout)
                Login::where('user_id', $user->id)
                    ->where('user_type', $userType)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Password has been reset successfully. Please login with your new password.',
                    'data' => [
                        'email' => $email,
                        'user_type' => $userType
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend OTP (dengan rate limiting)
     */
    public function resendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'user_type' => 'required|in:admin,pengguna'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cek apakah ada OTP aktif yang baru saja dikirim (minimal 1 menit yang lalu)
            $recentOtp = PasswordResetOtp::where('email', $request->email)
                ->where('user_type', $request->user_type)
                ->where('created_at', '>', now()->subMinute())
                ->first();

            if ($recentOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait at least 1 minute before requesting a new OTP'
                ], 429);
            }

            // Gunakan method requestOtp yang sudah ada
            return $this->requestOtp($request);

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan status OTP (untuk debugging atau admin)
     */
    public function getOtpStatus(Request $request)
    {
        try {
            // Support both query parameters (GET) dan form-data/JSON (POST)
            $email = $request->query('email') ?: $request->input('email');
            $userType = $request->query('user_type') ?: $request->input('user_type');
            
            if (!$email || !$userType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and user_type parameters are required',
                    'examples' => [
                        'GET' => 'GET /api/auth/forgot-password/otp-status?email=user@example.com&user_type=pengguna',
                        'POST' => 'POST /api/auth/forgot-password/otp-status with JSON body: {"email": "user@example.com", "user_type": "pengguna"}'
                    ]
                ], 422);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email format'
                ], 422);
            }

            if (!in_array($userType, ['admin', 'pengguna'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_type must be either admin or pengguna'
                ], 422);
            }

            $latestOtp = PasswordResetOtp::where('email', $email)
                ->where('user_type', $userType)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'No OTP found for this email'
                ], 404);
            }

            $status = 'Active';
            if ($latestOtp->is_used) {
                $status = 'Used';
            } elseif (!$latestOtp->is_active) {
                $status = 'Inactive';
            } elseif ($latestOtp->expires_at < now()) {
                $status = 'Expired';
            }

            $timeRemaining = 'Expired';
            if ($latestOtp->expires_at > now()) {
                $diff = now()->diffInMinutes($latestOtp->expires_at);
                $timeRemaining = $diff < 1 ? 'Less than 1 minute' : $diff . ' minutes';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'email' => $latestOtp->email,
                    'status' => $status,
                    'created_at' => $latestOtp->created_at,
                    'expires_at' => $latestOtp->expires_at,
                    'time_remaining' => $timeRemaining,
                    'is_valid' => $latestOtp->is_active && !$latestOtp->is_used && $latestOtp->expires_at > now()
                ]
            ]);

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OTP status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin method - Mendapatkan statistik OTP
     */
    public function getOtpStatistics(Request $request)
    {
        try {
            $stats = [
                'total_active' => PasswordResetOtp::where('is_active', true)->count(),
                'total_used' => PasswordResetOtp::where('is_used', true)->count(),
                'total_expired' => PasswordResetOtp::where('expires_at', '<', now())->count(),
                'requests_today' => PasswordResetOtp::whereDate('created_at', today())->count(),
            ];

            $total = PasswordResetOtp::count();
            $used = PasswordResetOtp::where('is_used', true)->count();
            $stats['success_rate'] = $total > 0 ? round(($used / $total) * 100, 2) : 0;
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OTP statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin method - Cleanup expired OTP
     */
    public function cleanupExpiredOtp(Request $request)
    {
        try {
            $cleanedCount = PasswordResetOtp::where('expires_at', '<', now())
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$cleanedCount} expired OTP records"
            ]);

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup expired OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
    * Generate secure OTP code
    */
    private function generateSecureOtpCode()
    {
        // Generate cryptographically secure random number
        $otp = '';
        for ($i = 0; $i < 6; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    /**
     * Helper method untuk generate OTP code
     */
    private function generateOtpCode()
    {
        return sprintf('%06d', random_int(100000, 999999));
    }

    public function testEmail(Request $request)
    {
        try {
            $email = $request->input('email', 'test@example.com');
            
            Log::info('Testing email configuration', ['email' => $email]);

            $data = [
                'name' => 'Test User',
                'otp_code' => '123456',
                'expires_in' => '10 minutes'
            ];

            Mail::send('emails.otp', $data, function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test OTP Email - INDOMAS');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
                'email' => $email
            ]);

        } catch (\Exception $e) {
            Log::error('Test email failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test email failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}