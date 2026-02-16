<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Layer; // Tambahkan Model Layer
use Illuminate\Support\Facades\DB;
use App\Jobs\AnalyzeOverlapsJob;

class StatisticController extends Controller
{
    public function index(Request $request)
    {
        // 1. Set waktu eksekusi
        set_time_limit(300); 

        // Ambil Daftar Layer untuk Filter
        $layers = Layer::orderBy('name', 'asc')->get();
        $layerId = $request->input('layer_id');

        // ==================================================================================
        // DEFINISI LOGIKA PENCARIAN (SQL EXPRESSION)
        // ==================================================================================

        // Logic untuk mendeteksi Tipe Hak
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

        // Logic untuk mendeteksi Nama Desa/Kelurahan
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

        // Filter Layer
        if ($layerId) {
            $queryHak->where('layer_id', $layerId);
        }

        $statsHak = $queryHak
            ->groupBy(DB::raw($hakExpression))
            ->orderBy('total', 'desc')
            ->get();

        // ==================================================================================
        // 3. QUERY STATISTIK PER DESA (LUAS ASET)
        // ==================================================================================
        $queryDesa = DB::table('spatial_features')
            ->select(
                DB::raw("$desaExpression as desa"),
                DB::raw("COUNT(*) as total_bidang"),
                DB::raw("SUM(ST_Area(geom::geography)) / 10000 as luas_hektar")
            );

        // Filter Layer
        if ($layerId) {
            $queryDesa->where('layer_id', $layerId);
        }
        
        $statsDesa = $queryDesa
            ->groupBy(DB::raw($desaExpression))
            ->havingRaw("$desaExpression != 'Tanpa Desa'")
            ->orderBy('total_bidang', 'desc')
            ->limit(20)
            ->get();

        // ==================================================================================
        // 4. ANALISIS TUMPANG TINDIH (DATA DETAIL)
        // ==================================================================================
        $overlapQuery = DB::table('overlap_results');

        // Filter Layer pada Overlap (Join ke spatial_features untuk cek layer_id aset pertama)
        if ($layerId) {
            $overlapQuery->join('spatial_features', 'overlap_results.id_1', '=', 'spatial_features.id')
                         ->where('spatial_features.layer_id', $layerId)
                         ->select('overlap_results.*');
        }
        
        $overlaps = $overlapQuery->orderBy('luas_overlap', 'desc')->paginate(50); 

        // Info Update Terakhir
        $lastUpdate = DB::table('overlap_results')->latest('created_at')->value('created_at');
        
        // Total Luas Terpetakan
        $totalLuasTerpetakan = $statsHak->sum('luas_m2') / 10000; 

        // ==================================================================================
        // 5. TOP 10 DESA DENGAN TUMPANG TINDIH TERBANYAK
        // ==================================================================================
        $queryTopOverlap = DB::table('overlap_results')
            ->select(
                'overlap_results.desa', 
                DB::raw('COUNT(*) as total_kasus'), 
                DB::raw('SUM(overlap_results.luas_overlap) as total_luas')
            )
            ->groupBy('overlap_results.desa')
            ->orderBy('total_kasus', 'desc')
            ->limit(10);

        if ($layerId) {
            $queryTopOverlap->join('spatial_features', 'overlap_results.id_1', '=', 'spatial_features.id')
                            ->where('spatial_features.layer_id', $layerId);
        }
        
        $topOverlapVillages = $queryTopOverlap->get();

        return view('admin.statistic.index', compact(
            'statsHak', 'statsDesa', 'overlaps', 'totalLuasTerpetakan', 
            'layers', 'layerId', 'lastUpdate', 'topOverlapVillages'
        ));
    }

    // Trigger Job Background (Tetap Ada)
    public function runAnalysis()
    {
        AnalyzeOverlapsJob::dispatch();
        return back()->with('success', 'Analisis sedang berjalan di background. Mohon tunggu beberapa saat dan refresh halaman.');
    }
}