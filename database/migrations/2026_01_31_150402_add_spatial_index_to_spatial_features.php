<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('spatial_features', function (Blueprint $table) {
                // Coba tambahkan index. Jika sudah ada, akan masuk block catch.
                $table->spatialIndex('geom');
            });
        } catch (\Throwable $e) {
            // Index sudah ada? Abaikan saja, lanjut.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('spatial_features', function (Blueprint $table) {
                $table->dropSpatialIndex(['geom']);
            });
        } catch (\Throwable $e) {
            // Abaikan jika gagal drop
        }
    }
};