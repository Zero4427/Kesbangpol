<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Akses\Login;
use App\Services\OtpService;
use App\Rules\ValidOtpToken;
use App\Models\Akses\PasswordResetOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImprovedPasswordResetController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function requestOtp(Request $request)
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

            $email = $request->email;
            $userType = $request->user_type;

            // Find user
            $user = $this->otpService->findUser($email, $userType);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found in our records'
                ], 404);
            }

            // Check if admin is active
            if ($userType === 'admin' && isset($user->is_active) && !$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin account is inactive'
                ], 403);
            }

            // Check rate limiting
            if (!$this->otpService->checkRateLimit($email, $userType)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Please wait 15 minutes before trying again.',
                    'retry_after' => 15 * 60
                ], 429);
            }

            // Create OTP
            $otp = $this->otpService->createOtp($email, $userType, $request->ip());

            // Send email
            $this->otpService->sendOtpEmail($email, $otp->otp_code, $user, $userType);

            Log::info('OTP requested', [
                'email' => $email,
                'user_type' => $userType,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP has been sent to your email address',
                'data' => [
                    'email' => $email,
                    'expires_at' => $otp->expires_at->toISOString(),
                    'expires_in_minutes' => config('otp.expiry_minutes', 2)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('OTP Request Error', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.'
            ], 500);
        }
    }

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

            // Verify OTP
            $otp = $this->otpService->verifyOtp($email, $otpCode, $userType);

            if (!$otp) {
                Log::warning('Invalid OTP attempt', [
                    'email' => $email,
                    'user_type' => $userType,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 400);
            }

            // Generate token
            $token = $this->otpService->generateOtpToken($email, $otpCode, $userType);

            Log::info('OTP verified successfully', [
                'email' => $email,
                'user_type' => $userType,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'email' => $email,
                    'user_type' => $userType,
                    'otp_token' => $token,
                    'expires_at' => $otp->expires_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('OTP Verification Error', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed'
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'otp_token' => ['required', 'string', new ValidOtpToken],
                'new_password' => 'required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Decode token
            $tokenData = $this->otpService->decodeOtpToken($request->otp_token);
            if (!$tokenData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 400);
            }

            extract($tokenData); // $email, $otpCode, $userType

            // Verify OTP again
            $otp = $this->otpService->verifyOtp($email, $otpCode, $userType);
            if (!$otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP token'
                ], 400);
            }

            // Update password
            DB::beginTransaction();
            
            try {
                $user = $this->otpService->findUser($email, $userType);
                if (!$user) {
                    throw new \Exception('User not found');
                }

                if ($userType === 'admin') {
                    $user->update(['password_admin' => Hash::make($request->new_password)]);
                } else {
                    $user->update(['password_pengguna' => Hash::make($request->new_password)]);
                }

                // Mark OTP as used
                $otp->update([
                    'is_used' => true,
                    'is_active' => false
                ]);

                // Force logout all sessions
                Login::where('user_id', $user->id)
                    ->where('user_type', $userType)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                DB::commit();

                Log::info('Password reset successfully', [
                    'email' => $email,
                    'user_type' => $userType,
                    'ip' => $request->ip()
                ]);

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
            Log::error('Password Reset Error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed'
            ], 500);
        }
    }

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

            $email = $request->email;
            $userType = $request->user_type;

            // Check if there's a recent OTP (within 1 minute)
            $recentOtp = PasswordResetOtp::where('email', $email)
                ->where('user_type', $userType)
                ->where('created_at', '>', now()->subMinute())
                ->first();

            if ($recentOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait at least 1 minute before requesting a new OTP'
                ], 429);
            }

            // Reuse requestOtp method
            return $this->requestOtp($request);

        } catch (\Exception $e) {
            Log::error('OTP Resend Error', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP'
            ], 500);
        }
    }

    public function getOtpStatus(Request $request)
    {
        try {
            $email = $request->query('email') ?: $request->input('email');
            $userType = $request->query('user_type') ?: $request->input('user_type');
            
            if (!$email || !$userType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email and user_type parameters are required',
                    'examples' => [
                        'GET' => 'GET /api/auth/forgot-password/otp-status?email=user@example.com&user_type=pengguna',
                        'POST' => 'POST /api/auth/forgot-password/otp-status with JSON body: {\"email\": \"user@example.com\", \"user_type\": \"pengguna\"}'
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
            Log::error('Get OTP Status Error', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'user_type' => $request->user_type ?? 'unknown',
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OTP status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
            Log::error('Get OTP Statistics Error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OTP statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
            Log::error('Cleanup OTP Error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup expired OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}