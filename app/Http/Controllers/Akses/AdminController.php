<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Akses\Admin;
use App\Models\Akses\Login;
use App\Models\Akses\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function createAdmin(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Only super admin can create new admin'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_admin' => 'required|string|max:255',
            'email_admin' => 'required|string|email|max:255|unique:admins,email_admin',
            'password_admin' => 'required|string|min:8|confirmed',
            'level_admin' => 'required|in:super_admin,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
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
                'status' => 'success',
                'message' => 'Admin created successfully',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'nama_admin' => $admin->nama_admin,
                        'email_admin' => $admin->email_admin,
                        'level_admin' => $admin->level_admin,
                        'is_active' => $admin->is_active,
                        'created_at' => $admin->created_at
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllAdmins(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Only super admin can view all admins'
            ], 403);
        }

        try {
            $admins = Admin::select('id', 'nama_admin', 'email_admin', 'level_admin', 'is_active', 'created_at')
                          ->orderBy('created_at', 'desc')
                          ->get();

            // Tambahkan informasi login terakhir
            foreach ($admins as $admin) {
                $lastLogin = Login::where('user_id', $admin->id)
                                 ->where('user_type', 'admin')
                                 ->orderBy('login_at', 'desc')
                                 ->first();
                
                $admin->last_login = $lastLogin ? $lastLogin->login_at : null;
                $admin->is_online = $lastLogin ? $lastLogin->is_active : false;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'admins' => $admins,
                    'total' => $admins->count(),
                    'active_admins' => $admins->where('is_active', true)->count(),
                    'online_admins' => $admins->where('is_online', true)->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get admins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAdminStatus(Request $request, $id)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Only super admin can update admin status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::findOrFail($id);

            // Cek apakah admin yang ingin diupdate adalah diri sendiri
            if ($admin->id === $currentLogin->user_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot deactivate your own account'
                ], 400);
            }

            $admin->update(['is_active' => $request->is_active]);

            // Jika admin dinonaktifkan, logout semua sesinya
            if (!$request->is_active) {
                Login::where('user_id', $admin->id)
                     ->where('user_type', 'admin')
                     ->where('is_active', true)
                     ->update(['is_active' => false]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Admin status updated successfully',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'nama_admin' => $admin->nama_admin,
                        'is_active' => $admin->is_active
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update admin status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAdmin(Request $request, $id)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_admin' => 'sometimes|required|string|max:255',
            'email_admin' => 'sometimes|required|string|email|max:255|unique:admins,email_admin,' . $id,
            'password_admin' => 'sometimes|nullable|string|min:8|confirmed',
            'level_admin' => 'sometimes|required|in:super_admin,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::findOrFail($id);
            
            $updateData = $request->only(['nama_admin', 'email_admin', 'level_admin']);
            
            if ($request->filled('password_admin')) {
                $updateData['password_admin'] = Hash::make($request->password_admin);
                
                // Logout semua sesi admin jika password diubah
                Login::where('user_id', $admin->id)
                     ->where('user_type', 'admin')
                     ->where('is_active', true)
                     ->update(['is_active' => false]);
            }

            $admin->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Admin updated successfully',
                'data' => [
                    'admin' => $admin->makeHidden(['password_admin'])
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAdmin(Request $request, $id)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $admin = Admin::findOrFail($id);

            // Cek apakah admin yang ingin dihapus adalah diri sendiri
            if ($admin->id === $currentLogin->user_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete your own account'
                ], 400);
            }

            // Hapus semua sesi login admin
            Login::where('user_id', $admin->id)
                 ->where('user_type', 'admin')
                 ->delete();

            // Hapus admin
            $admin->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Admin deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllUsers(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || !in_array($currentLogin->role, [1, 2])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Admin access required'
            ], 403);
        }

        try {
            $users = Pengguna::select(
                'id', 'nama_pengguna', 'email_pengguna', 'alamat_pengguna', 
                'no_telpon_pengguna', 'created_at'
            )->orderBy('created_at', 'desc')->get();

            // Tambahkan informasi login terakhir
            foreach ($users as $user) {
                $lastLogin = Login::where('user_id', $user->id)
                                 ->where('user_type', 'pengguna')
                                 ->orderBy('login_at', 'desc')
                                 ->first();
                
                $user->last_login = $lastLogin ? $lastLogin->login_at : null;
                $user->is_online = $lastLogin ? $lastLogin->is_active : false;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $users,
                    'total' => $users->count(),
                    'online_users' => $users->where('is_online', true)->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLoginSessions(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Only super admin can view login sessions'
            ], 403);
        }

        try {
            $sessions = Login::with([
                    'pengguna:id,nama_pengguna,email_pengguna', 
                    'admin:id,nama_admin,email_admin'
                ])
                ->select('id', 'user_id', 'user_type', 'email', 'role', 'login_at', 'last_activity', 'expires_at', 'is_active', 'ip_address')
                ->orderBy('login_at', 'desc')
                ->paginate(50);

            // Format data untuk response
            $formattedSessions = $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'user_type' => $session->user_type,
                    'user_name' => $session->user_type === 'admin' 
                        ? $session->admin->nama_admin ?? 'N/A'
                        : $session->pengguna->nama_pengguna ?? 'N/A',
                    'email' => $session->email,
                    'role' => $session->role,
                    'role_name' => $this->getRoleName($session->role),
                    'login_at' => $session->login_at,
                    'last_activity' => $session->last_activity,
                    'expires_at' => $session->expires_at,
                    'is_active' => $session->is_active,
                    'ip_address' => $session->ip_address,
                    'session_duration' => $session->login_at && $session->last_activity 
                        ? $session->login_at->diffInMinutes($session->last_activity) . ' minutes'
                        : 'N/A'
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sessions' => $formattedSessions,
                    'pagination' => [
                        'current_page' => $sessions->currentPage(),
                        'total_pages' => $sessions->lastPage(),
                        'per_page' => $sessions->perPage(),
                        'total' => $sessions->total()
                    ],
                    'statistics' => [
                        'total_sessions' => $sessions->total(),
                        'active_sessions' => Login::where('is_active', true)->count(),
                        'admin_sessions' => Login::where('user_type', 'admin')->where('is_active', true)->count(),
                        'user_sessions' => Login::where('user_type', 'pengguna')->where('is_active', true)->count()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get login sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function terminateSession(Request $request, $sessionId)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Only super admin can terminate sessions'
            ], 403);
        }

        try {
            $session = Login::findOrFail($sessionId);

            // Cek apakah yang ingin diterminasi adalah sesi sendiri
            if ($session->id === $currentLogin->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot terminate your own session'
                ], 400);
            }

            $session->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Session terminated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to terminate session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function terminateAllUserSessions(Request $request, $userId, $userType)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            // Validasi user type
            if (!in_array($userType, ['admin', 'pengguna'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid user type'
                ], 400);
            }

            // Cek apakah yang ingin diterminasi adalah sesi sendiri
            if ($userType === 'admin' && $userId == $currentLogin->user_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot terminate your own sessions'
                ], 400);
            }

            $terminatedCount = Login::where('user_id', $userId)
                                  ->where('user_type', $userType)
                                  ->where('is_active', true)
                                  ->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => "Successfully terminated {$terminatedCount} sessions",
                'data' => [
                    'terminated_sessions' => $terminatedCount
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to terminate user sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSystemStats(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || !in_array($currentLogin->role, [1, 2])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $stats = [
                'users' => [
                    'total_users' => Pengguna::count(),
                    'online_users' => Login::where('user_type', 'pengguna')
                                          ->where('is_active', true)
                                          ->count(),
                    'new_users_today' => Pengguna::whereDate('created_at', today())->count(),
                    'new_users_week' => Pengguna::whereBetween('created_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])->count()
                ],
                'admins' => [
                    'total_admins' => Admin::count(),
                    'active_admins' => Admin::where('is_active', true)->count(),
                    'online_admins' => Login::where('user_type', 'admin')
                                           ->where('is_active', true)
                                           ->count(),
                    'super_admins' => Admin::where('level_admin', 'super_admin')
                                          ->where('is_active', true)
                                          ->count()
                ],
                'sessions' => [
                    'total_active_sessions' => Login::where('is_active', true)->count(),
                    'expired_sessions' => Login::where('expires_at', '<', now())
                                              ->where('is_active', true)
                                              ->count(),
                    'sessions_today' => Login::whereDate('login_at', today())->count(),
                    'sessions_week' => Login::whereBetween('login_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])->count()
                ],
                'system' => [
                    'server_time' => now()->toDateTimeString(),
                    'timezone' => config('app.timezone'),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version()
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get system stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cleanupExpiredSessions(Request $request)
    {
        $currentLogin = $this->getCurrentLogin($request);
        if (!$currentLogin || $currentLogin->role !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $cleanedCount = Login::where('expires_at', '<', now())
                                ->where('is_active', true)
                                ->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => "Successfully cleaned up {$cleanedCount} expired sessions",
                'data' => [
                    'cleaned_sessions' => $cleanedCount
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cleanup expired sessions',
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

    // Helper method untuk mendapatkan nama role
    private function getRoleName($role)
    {
        switch ($role) {
            case 1:
                return 'Super Admin';
            case 2:
                return 'Admin';
            case 3:
                return 'Pengguna';
            default:
                return 'Unknown';
        }
    }
}