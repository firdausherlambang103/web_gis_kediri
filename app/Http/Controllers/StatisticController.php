<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use Illuminate\Support\Facades\DB;

class StatisticController extends Controller
{
    public function index(Request $request)
    {
        // Filter Wilayah (Optional)
        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa');

        // 1. STATISTIK TIPE HAK
        // Menggunakan operator ->> untuk mengambil teks dari JSONB di PostgreSQL
        $queryHak = DB::table('spatial_features')
            ->select(
                DB::raw("COALESCE(NULLIF(properties->'raw_data'->>'TIPE_HAK', ''), 'BELUM ADA HAK') as label"),
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(ST_Area(geom::geography)) as luas_m2")
            );

        if ($kecamatan) $queryHak->whereRaw("properties->'raw_data'->>'KECAMATAN' ILIKE ?", ['%'.$kecamatan.'%']);
        if ($desa) $queryHak->whereRaw("properties->'raw_data'->>'KELURAHAN' ILIKE ?", ['%'.$desa.'%']);

        $statsHak = $queryHak->groupBy('label')->orderBy('total', 'desc')->get();

        // 2. STATISTIK PER DESA
        $queryDesa = DB::table('spatial_features')
            ->select(
                DB::raw("properties->'raw_data'->>'KELURAHAN' as desa"),
                DB::raw("COUNT(*) as total_bidang"),
                DB::raw("SUM(ST_Area(geom::geography)) / 10000 as luas_hektar")
            )
            ->whereNotNull(DB::raw("properties->'raw_data'->>'KELURAHAN'"));

        if ($kecamatan) $queryDesa->whereRaw("properties->'raw_data'->>'KECAMATAN' ILIKE ?", ['%'.$kecamatan.'%']);
        
        $statsDesa = $queryDesa->groupBy('desa')->orderBy('total_bidang', 'desc')->limit(20)->get();

        // 3. ANALISIS TUMPANG TINDIH (FIXED POSTGRESQL SYNTAX)
        $overlapQuery = DB::table('spatial_features as a')
            ->join('spatial_features as b', function($join) {
                // PERBAIKAN 1: Gunakan whereRaw untuk kondisi boolean ST_Intersects
                // Jangan gunakan on(..., '>', 0) karena return value-nya boolean
                $join->whereRaw('ST_Intersects(a.geom, b.geom)')
                     ->on('a.id', '<', 'b.id'); // Mencegah duplikasi (A-B dan B-A)
            })
            ->select(
                'a.name as aset_1',
                'b.name as aset_2',
                'a.id as id_1',
                'b.id as id_2',
                // PERBAIKAN 2: Tambahkan prefix 'a.' untuk menghindari ambiguitas
                DB::raw("a.properties->'raw_data'->>'KELURAHAN' as desa"),
                DB::raw("ST_Area(ST_Intersection(a.geom, b.geom)::geography) as luas_tumpang_tindih")
            )
            // Filter hanya overlap yang signifikan (> 5 m2)
            ->whereRaw("ST_Area(ST_Intersection(a.geom, b.geom)::geography) > 5");

        if ($desa) {
            // PERBAIKAN 3: Gunakan ILIKE untuk case-insensitive search
            $overlapQuery->whereRaw("a.properties->'raw_data'->>'KELURAHAN' ILIKE ?", ['%'.$desa.'%']);
        } else {
            $overlapQuery->limit(50);
        }

        $overlaps = $overlapQuery->orderBy('luas_tumpang_tindih', 'desc')->get();

        // 4. TOTAL LUAS TERPETAKAN
        $totalLuasTerpetakan = $statsHak->sum('luas_m2') / 10000; // Dalam Hektar

        return view('admin.statistic.index', compact(
            'statsHak', 'statsDesa', 'overlaps', 'totalLuasTerpetakan', 'kecamatan', 'desa'
        ));
    }
}