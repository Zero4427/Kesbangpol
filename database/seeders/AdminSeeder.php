<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Akses\Admin;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::create([
            'nama_admin' => 'Super Admin',
            'email_admin' => 'superadmin@example.com',
            'password_admin' => Hash::make('password123'),
            'level_admin' => 'super_admin',
            'is_active' => true,
        ]);

        Admin::create([
            'nama_admin' => 'Admin User',
            'email_admin' => 'admin@example.com',
            'password_admin' => Hash::make('password123'),
            'level_admin' => 'admin',
            'is_active' => true,
        ]);

        $this->command->info('Admin users created successfully!');
        $this->command->info('Super Admin: superadmin@example.com / password123');
        $this->command->info('Admin: admin@example.com / password123');
    }
}