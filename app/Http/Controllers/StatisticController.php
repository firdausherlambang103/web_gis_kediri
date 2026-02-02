<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\AnalyzeOverlapsJob;

class StatisticController extends Controller
{
    public function index(Request $request)
    {
        // 1. Set waktu eksekusi agar tidak timeout saat kalkulasi berat
        set_time_limit(300); 

        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa');

        // ==================================================================================
        // DEFINISI LOGIKA PENCARIAN (SQL EXPRESSION)
        // Kita simpan string SQL ini ke variabel agar bisa dipakai di SELECT dan GROUP BY
        // ==================================================================================

        // Logic untuk mendeteksi Tipe Hak (Prioritas: TIPEHAK -> tipehak -> TIPE_HAK -> HAK)
        $hakExpression = "
            COALESCE(
                NULLIF(properties->'raw_data'->>'TIPEHAK', ''), 
                NULLIF(properties->'raw_data'->>'tipehak', ''), 
                NULLIF(properties->'raw_data'->>'TIPE_HAK', ''), 
                NULLIF(properties->'raw_data'->>'HAK', ''), 
                NULLIF(properties->'raw_data'->>'hak', ''), 
                NULLIF(properties->'raw_data'->>'STATUS', ''), 
                'BELUM ADA HAK'
            )
        ";

        // Logic untuk mendeteksi Nama Desa/Kelurahan (Prioritas: KELURAHAN -> kelurahan -> DESA)
        $desaExpression = "
            COALESCE(
                NULLIF(properties->'raw_data'->>'KELURAHAN', ''),
                NULLIF(properties->'raw_data'->>'kelurahan', ''), 
                NULLIF(properties->'raw_data'->>'DESA', ''),
                NULLIF(properties->'raw_data'->>'desa', ''),
                NULLIF(properties->'raw_data'->>'NAMOBJ', ''),
                NULLIF(properties->'raw_data'->>'WADMKD', ''), 
                'Tanpa Desa'
            )
        ";

        // ==================================================================================
        // 2. QUERY STATISTIK TIPE HAK
        // ==================================================================================
        $queryHak = DB::table('spatial_features')
            ->select(
                DB::raw("$hakExpression as label"),
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(ST_Area(geom::geography)) as luas_m2")
            );

        // Filter Wilayah (Cari text global agar aman dari perbedaan key KECAMATAN vs WADMKC)
        if ($kecamatan) {
            $queryHak->whereRaw("properties::text ILIKE ?", ['%' . $kecamatan . '%']);
        }
        if ($desa) {
            $queryHak->whereRaw("properties::text ILIKE ?", ['%' . $desa . '%']);
        }

        // GROUP BY wajib menggunakan raw expression yang sama persis dengan SELECT di Postgres
        $statsHak = $queryHak
            ->groupBy(DB::raw($hakExpression))
            ->orderBy('total', 'desc')
            ->get();

        // ==================================================================================
        // 3. QUERY STATISTIK PER DESA
        // ==================================================================================
        $queryDesa = DB::table('spatial_features')
            ->select(
                DB::raw("$desaExpression as desa"),
                DB::raw("COUNT(*) as total_bidang"),
                DB::raw("SUM(ST_Area(geom::geography)) / 10000 as luas_hektar")
            );

        // Filter Kecamatan
        if ($kecamatan) {
            $queryDesa->whereRaw("properties::text ILIKE ?", ['%' . $kecamatan . '%']);
        }
        
        // GROUP BY & HAVING
        $statsDesa = $queryDesa
            ->groupBy(DB::raw($desaExpression))
            ->havingRaw("$desaExpression != 'Tanpa Desa'")
            ->orderBy('total_bidang', 'desc')
            ->limit(20)
            ->get();

        // ==================================================================================
        // 4. ANALISIS TUMPANG TINDIH (BACA DARI TABEL HASIL JOB)
        // ==================================================================================
        $overlapQuery = DB::table('overlap_results');

        if ($desa) {
            $overlapQuery->where('desa', 'ILIKE', '%' . $desa . '%');
        }
        if ($kecamatan) {
            $overlapQuery->where('kecamatan', 'ILIKE', '%' . $kecamatan . '%');
        }
        
        $overlaps = $overlapQuery->orderBy('luas_overlap', 'desc')->paginate(50); 

        // Info Update Terakhir
        $lastUpdate = DB::table('overlap_results')->latest('created_at')->value('created_at');
        
        // Total Luas Terpetakan
        $totalLuasTerpetakan = $statsHak->sum('luas_m2') / 10000; 

        return view('admin.statistic.index', compact(
            'statsHak', 'statsDesa', 'overlaps', 'totalLuasTerpetakan', 'kecamatan', 'desa', 'lastUpdate'
        ));
    }

    // Trigger Job Background
    public function runAnalysis()
    {
        AnalyzeOverlapsJob::dispatch();
        return back()->with('success', 'Analisis sedang berjalan di background. Mohon tunggu beberapa saat dan refresh halaman.');
    }
}