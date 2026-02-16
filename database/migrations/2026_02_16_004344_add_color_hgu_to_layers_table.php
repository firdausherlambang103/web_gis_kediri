<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('layers', function (Blueprint $table) {
            // Tambah kolom warna untuk HGU (Default Orange Tua)
            $table->string('color_hgu', 7)->nullable()->default('#fd7e14');
        });
    }

    public function down()
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropColumn('color_hgu');
        });
    }
};