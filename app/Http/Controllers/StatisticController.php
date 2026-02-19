<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature; // Model Aset
use App\Models\Layer;          // Model Layer
use Illuminate\Support\Facades\DB;
use App\Jobs\AnalyzeOverlapsJob;
use App\Helpers\LogHelper;     // Helper Log

// --- IMPORT LIBRARY EXCEL ---
use App\Exports\OverlapExport;
use Maatwebsite\Excel\Facades\Excel;

class StatisticController extends Controller
{
    /**
     * Halaman Utama Statistik & Dashboard
     */
    public function index(Request $request)
    {
        // 1. Set waktu eksekusi agar tidak timeout saat query berat
        set_time_limit(300); 

        // 2. Ambil Filter
        $layers = Layer::where('is_active', true)->orderBy('name', 'asc')->get();
        $layerId = $request->input('layer_id');

        // 3. Statistik Ringkas (Untuk Kartu Atas Dashboard)
        $totalAset = SpatialFeature::count();
        $totalLayer = $layers->count();
        $totalOverlap = DB::table('overlap_results')->count();

        // ==================================================================================
        // DEFINISI LOGIKA PENCARIAN (SQL EXPRESSION) - UNTUK GRAFIK & TABEL
        // ==================================================================================

        // Logic untuk mendeteksi Tipe Hak (Normalisasi berbagai kemungkinan key JSON)
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
        // 4. QUERY STATISTIK TIPE HAK
        // ==================================================================================
        $queryHak = DB::table('spatial_features')
            ->select(
                DB::raw("$hakExpression as label"),
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(ST_Area(geom::geography)) as luas_m2")
            );

        if ($layerId) $queryHak->where('layer_id', $layerId);

        $statsHak = $queryHak
            ->groupBy(DB::raw($hakExpression))
            ->orderBy('total', 'desc')
            ->get();

        // ==================================================================================
        // 5. QUERY STATISTIK PER DESA
        // ==================================================================================
        $queryDesa = DB::table('spatial_features')
            ->select(
                DB::raw("$desaExpression as desa"),
                DB::raw("COUNT(*) as total_bidang"),
                DB::raw("SUM(ST_Area(geom::geography)) / 10000 as luas_hektar")
            );

        if ($layerId) $queryDesa->where('layer_id', $layerId);
        
        $statsDesa = $queryDesa
            ->groupBy(DB::raw($desaExpression))
            ->havingRaw("$desaExpression != 'Tanpa Desa'")
            ->orderBy('total_bidang', 'desc')
            ->limit(20)
            ->get();

        // ==================================================================================
        // 6. ANALISIS TUMPANG TINDIH (DATA DETAIL)
        // ==================================================================================
        $overlapQuery = DB::table('overlap_results')
            ->select('overlap_results.*');

        // Filter Layer pada Overlap
        if ($layerId) {
            $overlapQuery->join('spatial_features', 'overlap_results.id_1', '=', 'spatial_features.id')
                         ->where('spatial_features.layer_id', $layerId);
        }
        
        $overlaps = $overlapQuery->orderBy('luas_overlap', 'desc')->paginate(15); 

        // Info tambahan
        $lastUpdate = DB::table('overlap_results')->max('created_at');
        $totalLuasTerpetakan = $statsHak->sum('luas_m2') / 10000; // Hektar

        // ==================================================================================
        // 7. TOP 10 DESA DENGAN OVERLAP TERBANYAK
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
            'totalAset', 'totalLayer', 'totalOverlap', // Kartu Dashboard
            'statsHak', 'statsDesa', 'overlaps', 'totalLuasTerpetakan', 
            'layers', 'layerId', 'lastUpdate', 'topOverlapVillages'
        ));
    }

    /**
     * Menjalankan Analisis (Trigger Python via Job)
     */
    public function runAnalysis()
    {
        // 1. Panggil Job Background
        AnalyzeOverlapsJob::dispatch();
        
        // 2. Catat Log
        LogHelper::record('RUN_ANALYSIS', 'System', 'Menjalankan analisis tumpang tindih (Python)');

        return back()->with('success', 'Analisis sedang berjalan di background. Mohon tunggu beberapa saat dan refresh halaman.');
    }

    /**
     * Export Data Overlap ke EXCEL (.xlsx)
     * Menggunakan Library Maatwebsite/Excel
     */
    public function exportOverlap(Request $request)
    {
        $layerId = $request->input('layer_id');
        
        // Nama File .xlsx
        $fileName = 'Laporan_Overlap_' . date('Y-m-d_H-i') . '.xlsx';

        // Catat Log
        LogHelper::record('EXPORT', 'Overlap Data', 'Export data overlap ke Excel (.xlsx)');

        // Download Excel menggunakan Class Export yang sudah dibuat
        return Excel::download(new OverlapExport($layerId), $fileName);
    }

    public function export()
    {
        return back()->with('info', 'Fitur export aset belum tersedia saat ini.');
    }
}