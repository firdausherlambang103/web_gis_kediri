<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('layers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#3388ff');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Update tabel spatial_features untuk relasi ke layers
        Schema::table('spatial_features', function (Blueprint $table) {
            $table->unsignedBigInteger('layer_id')->nullable()->after('id');
            $table->foreign('layer_id')->references('id')->on('layers')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('spatial_features', function (Blueprint $table) {
            $table->dropForeign(['layer_id']);
            $table->dropColumn('layer_id');
        });
        Schema::dropIfExists('layers');
    }
};