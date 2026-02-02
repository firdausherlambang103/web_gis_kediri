<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyzeOverlapsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Timeout job 10 menit agar tidak gagal di tengah jalan
    public $timeout = 600;

    public function handle()
    {
        Log::info("Memulai Analisis Overlap...");

        // 1. Bersihkan data lama
        DB::table('overlap_results')->truncate();

        // 2. Jalankan Query Berat PostGIS
        // Langsung insert select (Jauh lebih cepat daripada looping PHP)
        $query = "
            INSERT INTO overlap_results (aset_1, aset_2, id_1, id_2, desa, kecamatan, luas_overlap, created_at, updated_at)
            SELECT 
                a.name as aset_1,
                b.name as aset_2,
                a.id as id_1,
                b.id as id_2,
                a.properties->'raw_data'->>'KELURAHAN' as desa,
                a.properties->'raw_data'->>'KECAMATAN' as kecamatan,
                ST_Area(ST_Intersection(a.geom, b.geom)::geography) as luas_overlap,
                NOW(),
                NOW()
            FROM spatial_features a
            JOIN spatial_features b 
                ON ST_Intersects(a.geom, b.geom) 
                AND a.id < b.id
            WHERE ST_Area(ST_Intersection(a.geom, b.geom)::geography) > 1
        ";

        DB::statement($query);

        Log::info("Analisis Overlap Selesai.");
    }
}