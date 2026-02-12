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
        Schema::table('spatial_features', function (Blueprint $table) {
            // Tambahkan kolom layer_id (nullable agar aman untuk data lama)
            // constrained() akan otomatis menghubungkan ke tabel 'layers'
            $table->foreignId('layer_id')
                  ->nullable()
                  ->after('name')
                  ->constrained('layers')
                  ->onDelete('cascade'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spatial_features', function (Blueprint $table) {
            // Hapus foreign key dan kolom jika rollback
            $table->dropForeign(['layer_id']);
            $table->dropColumn('layer_id');
        });
    }
};