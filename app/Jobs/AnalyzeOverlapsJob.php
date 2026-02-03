<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AnalyzeOverlapsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // Unlimited time untuk worker

    public function handle()
    {
        // 1. Konfigurasi Awal
        ini_set('memory_limit', '2048M'); // Naikkan RAM PHP ke 2GB
        DB::disableQueryLog(); // Matikan log query Laravel (Hemat RAM)
        
        Log::info("START: Analisis Overlap 1 Juta Data...");

        // 2. Cek Checkpoint (Apakah ada proses sebelumnya yang belum selesai?)
        $lastId = Cache::get('overlap_analysis_last_id', 0);
        
        if ($lastId == 0) {
            // Jika mulai dari 0, bersihkan tabel hasil
            DB::table('overlap_results')->truncate();
            // Optimasi statistik index
            DB::statement("VACUUM ANALYZE spatial_features");
        } else {
            Log::info("RESUMING: Melanjutkan dari ID $lastId");
        }

        // 3. Batch Processing (Chunking)
        // Ambil ID secara berurutan, mulai dari ID terakhir yang diproses
        $batchSize = 200; // Kecilkan batch agar Database tidak stress
        
        $maxId = DB::table('spatial_features')->max('id');

        while ($lastId < $maxId) {
            
            // Ambil sekumpulan ID (Batching)
            $batchIds = DB::table('spatial_features')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id')
                ->toArray();

            if (empty($batchIds)) break;

            $idsString = implode(',', $batchIds);
            $currentMaxBatchId = end($batchIds);

            // 4. Query Spasial "Ringan"
            // Menggunakan CTE (Common Table Expression) untuk kejelasan dan performa
            $query = "
                INSERT INTO overlap_results (id_1, id_2, aset_1, aset_2, desa, kecamatan, luas_overlap, created_at, updated_at)
                SELECT 
                    a.id as id_1,
                    b.id as id_2,
                    a.name as aset_1,
                    b.name as aset_2,
                    COALESCE(a.properties->'raw_data'->>'KELURAHAN', a.properties->'raw_data'->>'kelurahan', '-') as desa,
                    COALESCE(a.properties->'raw_data'->>'KECAMATAN', a.properties->'raw_data'->>'kecamatan', '-') as kecamatan,
                    ST_Area(ST_Intersection(a.geom, b.geom)::geography) as luas_overlap,
                    NOW(),
                    NOW()
                FROM spatial_features a
                JOIN spatial_features b ON 
                    a.id < b.id             -- Hindari duplikasi dan self-join
                    AND a.geom && b.geom    -- GUNAKAN INDEX SPASIAL (Cek kotak luar dulu)
                WHERE 
                    a.id IN ($idsString)    -- Hanya proses batch ini
                    AND ST_Intersects(a.geom, b.geom) -- Cek detail geometri
                    AND ST_IsValid(a.geom) 
                    AND ST_IsValid(b.geom)
                HAVING 
                    ST_Area(ST_Intersection(a.geom, b.geom)::geography) > 1 -- Abaikan irisan < 1 meter
            ";

            try {
                DB::statement($query);
                
                // Simpan Checkpoint
                Cache::put('overlap_analysis_last_id', $currentMaxBatchId);
                $lastId = $currentMaxBatchId;

                // Log per 1000 data agar tidak spam
                if ($lastId % 1000 == 0) {
                    Log::info("Progress: ID $lastId / $maxId");
                }

                // Bersihkan Memori PHP
                unset($batchIds);
                gc_collect_cycles();

            } catch (\Exception $e) {
                Log::error("Error pada Batch ID $lastId: " . $e->getMessage());
                // Jangan stop, lanjut batch berikutnya (opsional, atau throw error)
                // throw $e; 
            }
        }

        // Selesai -> Hapus Checkpoint
        Cache::forget('overlap_analysis_last_id');
        Log::info("FINISH: Analisis Overlap Selesai Sepenuhnya.");
    }
}