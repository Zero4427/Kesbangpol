<?php

namespace App\Services;

use App\Models\Akses\PasswordResetOtp;
use App\Models\Akses\Admin;
use App\Models\Akses\Pengguna;
use App\Jobs\SendOtpEmail;
use Illuminate\Support\Facades\DB;

class OtpService
{
    /**
     * Generate secure OTP code
     */
    public function generateOtpCode(): string
    {
        $otp = '';
        for ($i = 0; $i < 6; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    /**
     * Find user by email and type
     */
    public function findUser(string $email, string $userType)
    {
        if ($userType === 'admin') {
            return Admin::where('email_admin', $email)->first();
        }
        
        return Pengguna::where('email_pengguna', $email)->first();
    }

    /**
     * Check rate limiting
     */
    public function checkRateLimit(string $email, string $userType, int $maxAttempts = 3, int $timeWindow = 15): bool
    {
        $recentRequests = PasswordResetOtp::where('email', $email)
            ->where('user_type', $userType)
            ->where('created_at', '>', now()->subMinutes($timeWindow))
            ->count();

        return $recentRequests < $maxAttempts;
    }

    /**
     * Create OTP record
     */
    public function createOtp(string $email, string $userType, string $ipAddress): PasswordResetOtp
    {
        return DB::transaction(function () use ($email, $userType, $ipAddress) {
            // Deactivate old OTPs
            PasswordResetOtp::where('email', $email)
                ->where('user_type', $userType)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Create new OTP
            return PasswordResetOtp::create([
                'email' => $email,
                'otp_code' => $this->generateOtpCode(),
                'user_type' => $userType,
                'expires_at' => now()->addMinutes(2),
                'ip_address' => $ipAddress
            ]);
        });
    }

    /**
     * Send OTP email
     */
    public function sendOtpEmail(string $email, string $otpCode, $user, string $userType): void
    {
        $name = $userType === 'admin' ? 
            ($user->nama_admin ?? 'Admin') : 
            ($user->nama_pengguna ?? 'User');

        SendOtpEmail::dispatch($email, $otpCode, $name);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(string $email, string $otpCode, string $userType): ?PasswordResetOtp
    {
        return PasswordResetOtp::where('email', $email)
            ->where('user_type', $userType)
            ->where('otp_code', $otpCode)
            ->where('is_active', true)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Generate OTP token
     */
    public function generateOtpToken(string $email, string $otpCode, string $userType): string
    {
        return base64_encode($email . '|' . $otpCode . '|' . $userType . '|' . time());
    }

    /**
     * Decode OTP token
     */
    public function decodeOtpToken(string $token): ?array
    {
        $decoded = base64_decode($token);
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 4) {
            return null;
        }

        [$email, $otpCode, $userType, $timestamp] = $parts;
        
        // Check if token is not older than 15 minutes
        if (time() - $timestamp > 900) {
            return null;
        }

        return compact('email', 'otpCode', 'userType');
    }
}