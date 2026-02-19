<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('spatial_features', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('properties'); // Kolom untuk simpan path file
        });
    }

    public function down()
    {
        Schema::table('spatial_features', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }
};
