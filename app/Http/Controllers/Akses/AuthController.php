<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Akses\Admin;
use App\Models\Akses\Pengguna;
use App\Models\Akses\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function registerPengguna(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_pengguna' => 'required|string|max:255',
            'email_pengguna' => 'required|string|email|max:255|unique:penggunas',
            'password_pengguna' => 'required|string|min:8|confirmed',
            'alamat_pengguna' => 'required|string',
            'no_telpon_pengguna' => 'required|string|max:15|unique:penggunas',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pengguna = Pengguna::create([
                'nama_pengguna' => $request->nama_pengguna,
                'email_pengguna' => $request->email_pengguna,
                'password_pengguna' => Hash::make($request->password_pengguna),
                'alamat_pengguna' => $request->alamat_pengguna,
                'no_telpon_pengguna' => $request->no_telpon_pengguna,
            ]);

            // Create login session
            $bearerToken = Str::random(60);
            
            $login = Login::create([
                'user_id' => $pengguna->id,
                'user_type' => 'pengguna',
                'email' => $pengguna->email_pengguna,
                'bearer_token' => $bearerToken,
                'role' => 'pengguna', // Role 3 untuk pengguna
                'login_at' => now(),
                'expires_at' => now()->addDays(30), // 30 hari untuk pengguna
                'is_active' => true,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengguna registered successfully',
                'data' => [
                    'pengguna' => $pengguna->makeHidden(['password_pengguna']),
                    'access_token' => $bearerToken,
                    'token_type' => 'Bearer',
                    'role' => 'pengguna',
                    'expires_at' => $login->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function registerAdmin(Request $request)
    {
        // Cek apakah yang melakukan request adalah super admin
        $currentLogin = $this->getCurrentLogin($request);
        $admin = $currentLogin ? Admin::find($currentLogin->user_id) : null;
        if (!$currentLogin || !$admin || $admin->level_admin !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only super admin can register new admin'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_admin' => 'required|string|max:255',
            'email_admin' => 'required|string|email|max:255|unique:admins',
            'password_admin' => 'required|string|min:8|confirmed',
            'level_admin' => 'required|in:super_admin,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::create([
                'nama_admin' => $request->nama_admin,
                'email_admin' => $request->email_admin,
                'password_admin' => Hash::make($request->password_admin),
                'level_admin' => $request->level_admin,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Admin registered successfully',
                'data' => [
                    'admin' => $admin->makeHidden(['password_admin'])
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function loginPengguna(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_pengguna' => 'required|string|email',
            'password_pengguna' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pengguna = Pengguna::where('email_pengguna', $request->email_pengguna)->first();

            if (!$pengguna || !Hash::check($request->password_pengguna, $pengguna->password_pengguna)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Nonaktifkan sesi lama jika ada
            Login::where('user_id', $pengguna->id)
                ->where('user_type', 'pengguna')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Cek apakah sudah ada entri login untuk email ini
            $existingLogin = Login::where('email', $pengguna->email_pengguna)
                ->where('user_id', $pengguna->id)
                ->where('user_type', 'pengguna')
                ->first();

            // Buat sesi login baru
            $bearerToken = Str::random(60);
            $expiresAt = now()->addDays(30);

            if ($existingLogin) {
            // Perbarui entri login yang ada
                $existingLogin->update([
                    'bearer_token' => $bearerToken,
                    'role' => 'pengguna',
                    'login_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'ip_address' => $request->ip(),
                    'last_activity' => now(),
                ]);
                $login = $existingLogin;
            } else {
                // Buat sesi login baru jika tidak ada entri sebelumnya
                $login = Login::create([
                    'user_id' => $pengguna->id,
                    'user_type' => 'pengguna',
                    'email' => $pengguna->email_pengguna,
                    'bearer_token' => $bearerToken,
                    'role' => 'pengguna',
                    'login_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'ip_address' => $request->ip(),
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'pengguna' => $pengguna->makeHidden(['password_pengguna']),
                    'access_token' => $bearerToken,
                    'token_type' => 'Bearer',
                    'role' => 'pengguna',
                    'expires_at' => $login->expires_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function loginAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_admin' => 'required|string|email',
            'password_admin' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::where('email_admin', $request->email_admin)->first();

            if (!$admin || !Hash::check($request->password_admin, $admin->password_admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Cek apakah admin aktif
            if (!$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin account is inactive'
                ], 403);
            }

            // Nonaktifkan sesi lama jika ada
            Login::where('user_id', $admin->id)
                ->where('user_type', 'admin')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Buat sesi login baru
            $bearerToken = Str::random(60);
            //$role = $admin->level_admin === 'super_admin' ? 1 : 2;

            // Cek apakah sudah ada entri login untuk email ini
            $existingLogin = Login::where('email', $admin->email_admin)
                ->where('user_id', $admin->id)
                ->where('user_type', 'admin')
                ->first();
            
            if ($existingLogin) {
            // Perbarui entri login yang ada
                $existingLogin->update([
                    'bearer_token' => $bearerToken,
                    'role' => 'admin',
                    'login_at' => now(),
                    'expires_at' => now()->addHours(8),
                    'is_active' => true,
                    'ip_address' => $request->ip(),
                    'last_activity' => now(),
                ]);
                $login = $existingLogin;
            } else {
                // Buat sesi login baru jika tidak ada entri sebelumnya
                $login = Login::create([
                    'user_id' => $admin->id,
                    'user_type' => 'admin',
                    'email' => $admin->email_admin,
                    'bearer_token' => $bearerToken,
                    'role' => 'admin',
                    'login_at' => now(),
                    'expires_at' => now()->addHours(8),
                    'is_active' => true,
                    'ip_address' => $request->ip(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'admin' => $admin->makeHidden(['password_admin']),
                    'access_token' => $bearerToken,
                    'token_type' => 'Bearer',
                    'role' => $admin->level_admin,
                    'expires_at' => $login->expires_at,
                    'redirect_url' => $admin->level_admin === 'super_admin' ? '/admin/cms' : '/admin/dashboard'
                ] 
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            if (!$currentLogin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active session found'
                ], 401);
            }

            // Nonaktifkan sesi login
            $currentLogin->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            if (!$currentLogin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $user = null;
            $role = '';

            if ($currentLogin->user_type === 'admin') {
                $user = Admin::find($currentLogin->user_id);
                $role = $user->level_admin ?? 'admin';
                $user = $user ? $user->makeHidden(['password_admin']) : null;
            } else {
                $user = Pengguna::find($currentLogin->user_id);
                $role = 'pengguna';
                $user = $user ? $user->makeHidden(['password_pengguna']) : null;
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'role' => $role,
                    'login_info' => [
                        'login_at' => $currentLogin->login_at,
                        'expires_at' => $currentLogin->expires_at,
                        'ip_address' => $currentLogin->ip_address
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refreshToken(Request $request)
    {
        try {
            $currentLogin = $this->getCurrentLogin($request);
            
            if (!$currentLogin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active session found'
                ], 401);
            }

            // Generate token baru
            $newBearerToken = Str::random(60);
            
            // Update token dan perpanjang waktu expired
            $expiresAt = $currentLogin->user_type === 'admin' 
                ? now()->addHours(8) 
                : now()->addDays(30);
                
            $currentLogin->update([
                'bearer_token' => $newBearerToken,
                'expires_at' => $expiresAt,
                'last_activity' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'access_token' => $newBearerToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper method untuk mendapatkan login session saat ini
    private function getCurrentLogin(Request $request)
    {
        $bearerToken = $request->bearerToken();
        
        if (!$bearerToken) {
            return null;
        }

        return Login::where('bearer_token', $bearerToken)
                   ->where('is_active', true)
                   ->where('expires_at', '>', now())
                   ->first();
    }

    // Method untuk validasi token (bisa digunakan di middleware)
    public function validateToken(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        
        if (!$currentLogin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        // Update last activity
        $currentLogin->update(['last_activity' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'user_type' => $currentLogin->user_type,
                'role' => $currentLogin->role,
                'expires_at' => $currentLogin->expires_at
            ]
        ]);
    }
}