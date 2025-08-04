<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sosial_medias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisasi_id')->constrained('organisasis')->onDelete('cascade');
            $table->string('platform'); // Instagram, Facebook, Twitter, etc
            $table->string('url');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sosial_medias');
    }
};