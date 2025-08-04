<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OtpRateLimit
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 3, int $decayMinutes = 15)
    {
        $email = $request->input('email');
        $userType = $request->input('user_type');
        $ip = $request->ip();
        
        if (!$email || !$userType) {
            return $next($request);
        }

        $key = "otp_rate_limit:{$email}:{$userType}:{$ip}";
        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'success' => false,
                'message' => "Too many OTP requests. Please try again in {$decayMinutes} minutes.",
                'retry_after' => $decayMinutes * 60
            ], 429);
        }

        Cache::put($key, $attempts + 1, $decayMinutes * 60);

        return $next($request);
    }
}