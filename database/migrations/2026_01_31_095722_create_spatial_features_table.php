<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spatial_features', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->json('properties')->nullable();
            $table->geometry('geom'); // Membutuhkan MySQL 8+ atau MariaDB 10.2+
            $table->timestamps();
            
            $table->spatialIndex('geom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spatial_features');
    }
};