<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('otp_code', 6); // 6 digit OTP
            $table->string('user_type'); // 'admin' atau 'pengguna'
            $table->timestamp('expires_at'); // OTP expire time (biasanya 5-10 menit)
            $table->boolean('is_used')->default(false); // apakah OTP sudah digunakan
            $table->boolean('is_active')->default(true); // apakah OTP masih aktif
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            // Index untuk performa
            $table->index(['email', 'user_type']);
            $table->index(['otp_code', 'is_active']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('password_reset_otps');
    }
};