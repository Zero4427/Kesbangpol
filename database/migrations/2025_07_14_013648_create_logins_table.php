<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginsTable extends Migration
{
    public function up()
    {
        Schema::create('logins', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->enum('role', ['admin', 'pengguna']); // 1 = admin, 2 = pengguna
            $table->string('bearer_token')->nullable();
            $table->unsignedBigInteger('user_id'); // ID dari admin atau pengguna
            $table->string('user_type'); // admin atau pengguna
            $table->timestamp('login_at')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('expires_at')->nullable(); // ganti dari token_expires_at agar konsisten
            $table->boolean('is_active')->default(true);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('logins');
    }
};