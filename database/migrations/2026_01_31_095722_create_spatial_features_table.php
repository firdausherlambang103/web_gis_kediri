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
            
            // Index nama agar pencarian cepat
            $table->string('name')->index(); 
            
            // Gunakan JSONB (Binary JSON) untuk performa tinggi di PostgreSQL
            $table->jsonb('properties')->nullable(); 
            
            // Definisi Geometri PostGIS (Tipe Geometry, SRID 4326/WGS84)
            // Ini akan membuat kolom geometry fisik, bukan sekadar text
            $table->geometry('geom', 'geometry', 4326)->spatialIndex(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spatial_features');
    }
};