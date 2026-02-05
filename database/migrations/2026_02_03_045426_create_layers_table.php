<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cek dulu biar tidak error "Table already exists" jika dijalankan manual
        if (!Schema::hasTable('layers')) {
            Schema::create('layers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('color', 20);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layers');
    }
};