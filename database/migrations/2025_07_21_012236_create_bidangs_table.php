<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bidangs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bidang');
            $table->text('deskripsi_bidang')->nullable();
            $table->string('gambar_karyawan')->nullable();
            $table->integer('jumlah_staf')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bidangs');
    }
};