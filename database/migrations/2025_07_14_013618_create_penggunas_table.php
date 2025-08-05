<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('penggunas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pengguna')->unique();
            $table->string('email_pengguna')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password_pengguna');
            $table->text('alamat_pengguna');
            $table->string('no_telpon_pengguna')->unique();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('penggunas');
    }
};