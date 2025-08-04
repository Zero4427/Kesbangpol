<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('struktur_pengurus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisasi_id')->constrained('organisasis')->onDelete('cascade');
            $table->string('nama_pengurus');
            $table->string('jabatan');
            $table->string('nomor_sk')->nullable();
            $table->string('nomor_keanggotaan')->nullable();
            $table->string('no_telpon')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('struktur_pengurus');
    }
};