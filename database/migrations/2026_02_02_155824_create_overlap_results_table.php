<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('overlap_results', function (Blueprint $table) {
            $table->id();
            $table->string('aset_1')->nullable();
            $table->string('aset_2')->nullable();
            $table->unsignedBigInteger('id_1');
            $table->unsignedBigInteger('id_2');
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->double('luas_overlap'); // Luas dalam meter persegi
            $table->timestamps();
            
            // Index untuk mempercepat filter di halaman statistik
            $table->index('desa');
            $table->index('kecamatan');
        });
    }

    public function down()
    {
        Schema::dropIfExists('overlap_results');
    }
};