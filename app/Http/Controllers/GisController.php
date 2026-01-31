<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GisController extends Controller
{
    // --- HALAMAN PETA ---
    public function index()
    {
        return view('admin.map');
    }

    // --- HALAMAN TABEL DATA ---
    public function indexTable(Request $request)
    {
        $search = $request->input('search');
        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa');

        $query = SpatialFeature::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('properties->raw_data->NIB', 'like', "%{$search}%");
            });
        }
        if ($kecamatan) $query->where('properties->raw_data->KECAMATAN', 'like', "%{$kecamatan}%");
        if ($desa) $query->where('properties->raw_data->KELURAHAN', 'like', "%{$desa}%");

        $data = $query->select('id', 'name', 'properties', 'created_at')
                      ->orderBy('id', 'desc')
                      ->paginate(15)
                      ->withQueryString();

        return view('admin.aset.index', compact('data', 'search', 'kecamatan', 'desa'));
    }

    // --- API PETA (OPTIMALISASI KECEPATAN) ---
    public function apiData(Request $request)
    {
        // 1. Cek Parameter Bounds (Viewport)
        if (!$request->has(['north', 'south', 'east', 'west'])) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        try {
            $n = $request->north;
            $s = $request->south;
            $e = $request->east;
            $w = $request->west;

            // 2. Polygon Viewport
            $polygonWKT = sprintf(
                "POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))",
                $w, $s, $e, $s, $e, $n, $w, $n, $w, $s
            );

            // 3. QUERY OPTIMIZED (ST_Simplify)
            // ST_Simplify(geom, 0.00005) akan mengurangi detail titik polygon yang tidak terlihat mata.
            // Ini mengurangi ukuran file JSON dari 10MB -> 500KB (Sangat Cepat!)
            $data = SpatialFeature::select(
                'id', 
                'name', 
                'properties',
                DB::raw('ST_AsGeoJSON(ST_Simplify(geom, 0.00005)) as geometry') 
            )
            ->whereRaw("ST_Intersects(geom, ST_GeomFromText(?))", [$polygonWKT])
            ->limit(3000) // Batas aman browser
            ->get();

            $features = [];
            foreach ($data as $item) {
                if (!$item->geometry) continue;

                $features[] = [
                    'type' => 'Feature',
                    'geometry' => json_decode($item->geometry),
                    'properties' => array_merge(
                        ['id' => $item->id, 'name' => $item->name],
                        $item->properties ?? [] 
                    )
                ];
            }

            return response()->json([
                'type' => 'FeatureCollection',
                'features' => $features,
                'count' => count($features)
            ]);

        } catch (\Exception $e) {
            Log::error('API Map Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // --- IMPORT SHP (Smart Batch Upload) ---
    public function storeShp(Request $request)
    {
        set_time_limit(0); ini_set('memory_limit', '-1'); ini_set('max_execution_time', 0);

        $request->validate([
            'shp_files' => 'required',
            'shp_files.*' => 'file|mimes:zip|max:102400'
        ]);

        $files = $request->file('shp_files');
        if (!is_array($files)) $files = [$files];

        $successCount = 0;
        $failedInfo = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $extractPath = null;

            try {
                $uniqueId = uniqid('shp_', true);
                $extractPath = storage_path('app/temp_shp/' . $uniqueId);
                if (!file_exists($extractPath)) mkdir($extractPath, 0777, true);

                $file->storeAs('temp_shp/' . $uniqueId, 'source.zip');
                $zip = new ZipArchive;
                if ($zip->open(storage_path('app/temp_shp/' . $uniqueId . '/source.zip')) === TRUE) {
                    $zip->extractTo($extractPath); $zip->close();
                } else { throw new \Exception('Gagal ekstrak ZIP.'); }

                $shpFiles = glob($extractPath . '/**/*.shp'); 
                if (empty($shpFiles)) $shpFiles = glob($extractPath . '/*.shp');
                if (empty($shpFiles)) throw new \Exception('File .shp tidak ditemukan.');
                $shpFile = $shpFiles[0];

                $geojsonFile = $extractPath . '/output.json';
                // Tangkap error OGR2OGR
                $cmd = "ogr2ogr -f GeoJSON -t_srs EPSG:4326 \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                $output = [];
                $returnVar = 0;
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile) || filesize($geojsonFile) == 0) {
                    throw new \Exception('Gagal Konversi SHP (Cek GDAL).');
                }

                $jsonContent = file_get_contents($geojsonFile);
                $geoData = json_decode($jsonContent, true);
                if (!isset($geoData['features'])) throw new \Exception('Format GeoJSON invalid.');

                DB::transaction(function () use ($geoData) {
                    foreach ($geoData['features'] as $feature) {
                        $geom = json_encode($feature['geometry']);
                        $props = $feature['properties'] ?? [];
                        $name = $props['nama'] ?? $props['NAME'] ?? $props['NIB'] ?? 'Import';
                        
                        DB::table('spatial_features')->insert([
                            'name' => $name,
                            'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                            // Simpan SRID 0 agar aman
                            'geom' => DB::raw("ST_GeomFromGeoJSON('$geom')"), 
                            'created_at' => now(), 'updated_at' => now()
                        ]);
                    }
                });

                $successCount++;
                $this->deleteDirectory($extractPath);

            } catch (\Exception $e) {
                $failedInfo[] = $originalName . ": " . $e->getMessage();
                Log::error("Import Gagal: " . $e->getMessage());
                if ($extractPath) $this->deleteDirectory($extractPath);
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            if (count($failedInfo) > 0) return response()->json(['status' => 'partial_error', 'message' => implode(', ', $failedInfo)], 422);
            return response()->json(['status' => 'success', 'message' => 'Berhasil']);
        }
        
        if (count($failedInfo) > 0) return back()->with('error', implode(', ', $failedInfo));
        return back()->with('success', "Berhasil import $successCount file!");
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    public function storeDraw(Request $request) {
        // (Sama seperti sebelumnya)
        try {
            DB::table('spatial_features')->insert([
                'name'=>$request->name, 'properties'=>json_encode(['type'=>$request->type,'description'=>$request->description,'color'=>'#ff0000']),
                'geom'=>DB::raw("ST_GeomFromGeoJSON('{$request->geometry}')"), 'created_at'=>now(), 'updated_at'=>now()
            ]);
            return response()->json(['status'=>'success']);
        } catch(\Exception $e) { return response()->json(['status'=>'error'],500); }
    }
}