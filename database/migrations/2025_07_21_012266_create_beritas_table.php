<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('beritas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('penggunas')->onDelete('cascade');
            $table->foreignId('organisasi_id')->constrained('organisasis')->onDelete('cascade');
            $table->string('nama_kegiatan');
            $table->string('lokasi_kegiatan');
            $table->date('tanggal_kegiatan');
            $table->text('deskripsi_kegiatan');
            $table->json('dokumentasi_kegiatan')->nullable(); // Array of image paths
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('beritas');
    }
};