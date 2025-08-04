<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Akses\Login;
use Symfony\Component\HttpFoundation\Response;

class BearerTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided'
            ], 401);
        }

        $login = Login::where('bearer_token', $bearerToken)
            ->active()
            ->first();

        if (!$login) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive token'
            ], 401);
        }

        // Tentukan pengguna berdasarkan user_type
        $authenticatedUser = null;
        if ($login->user_type === 'admin') {
            $authenticatedUser = $login->admin;
        } elseif ($login->user_type === 'pengguna') {
            $authenticatedUser = $login->pengguna;
        }

        if (!$authenticatedUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Inject user ke request->user() untuk kompatibilitas dengan Laravel auth
        $request->setUserResolver(function () use ($authenticatedUser) {
            return $authenticatedUser;
        });

        // Inject user polymorphic relation ke request
        $request->merge([
            'authenticated_user' => $authenticatedUser, // Admin atau Pengguna
            'user_role' => $login->role,
            'login_session' => $login
        ]);

        // (Opsional) Perpanjang masa aktif token (rolling session)
        $login->update(['expires_at' => now()->addMinutes(5)]);

        return $next($request);
    }
}