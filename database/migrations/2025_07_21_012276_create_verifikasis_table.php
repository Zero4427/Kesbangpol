<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisasi_id')->constrained('organisasis')->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('admins');
            $table->enum('status', ['proses', 'aktif', 'tidak aktif'])->default('proses');
            $table->text('catatan_admin')->nullable();
            $table->string('link_drive_admin')->nullable();
            $table->timestamp('tanggal_verifikasi')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('verifikasis');
    }
};