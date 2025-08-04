<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Akses\Login;
use Carbon\Carbon;

class CustomAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$roles
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $bearerToken = $request->bearerToken();
        
        if (!$bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required'
            ], 401);
        }

        // Cari session login berdasarkan token
        $loginSession = Login::where('bearer_token', $bearerToken)
                            ->where('is_active', true)
                            ->first();

        if (!$loginSession) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication token'
            ], 401);
        }

        // Cek apakah token sudah expired
        if (Carbon::now()->greaterThan($loginSession->expires_at)) {
            $loginSession->update(['is_active' => false]);
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication token has expired'
            ], 401);
        }

        // Cek role jika ada parameter role yang diberikan
        if (!empty($roles)) {
            $userRole = $this->getRoleString($loginSession->role);
            
            if (!in_array($userRole, $roles) && !in_array((string)$loginSession->role, $roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }
        }

        // Update last activity
        $loginSession->update(['last_activity' => now()]);

        // Tambahkan informasi login ke request untuk digunakan di controller
        $request->merge(['current_login' => $loginSession]);

        return $next($request);
    }

    /**
     * Convert role number to string
     */
    private function getRoleString($role)
    {
        switch ($role) {
            case 1:
                return 'super_admin';
            case 2:
                return 'admin';
            case 3:
                return 'pengguna';
            default:
                return 'unknown';
        }
    }
}