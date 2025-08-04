<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('organisasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('penggunas')->onDelete('cascade');
            $table->foreignId('kajian_id')->constrained('kajians')->onDelete('cascade');
            $table->string('nama_organisasi')->unique();
            $table->date('tanggal_berdiri');
            $table->text('deskripsi_organisasi');
            $table->string('logo_organisasi')->nullable();
            $table->string('file_persyaratan')->nullable();
            $table->enum('status_verifikasi', ['proses', 'aktif', 'tidak aktif'])->default('proses');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('organisasis');
    }
};