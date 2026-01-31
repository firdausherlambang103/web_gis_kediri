<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GisController extends Controller
{
    /**
     * 1. HALAMAN UTAMA PETA
     */
    public function index()
    {
        return view('admin.map');
    }

    /**
     * 2. HALAMAN TABEL DATA (Filter & Search)
     */
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
        if ($kecamatan) {
            $query->where('properties->raw_data->KECAMATAN', 'like', "%{$kecamatan}%");
        }
        if ($desa) {
            $query->where('properties->raw_data->KELURAHAN', 'like', "%{$desa}%");
        }

        $data = $query->select('id', 'name', 'properties', 'created_at')
                      ->orderBy('id', 'desc')
                      ->paginate(15)
                      ->withQueryString();

        return view('admin.aset.index', compact('data', 'search', 'kecamatan', 'desa'));
    }

    /**
     * 3. API PETA (SRID 0 - Cartesian)
     * Mengambil data berdasarkan viewport layar tanpa error Latitude/Longitude.
     */
    public function apiData(Request $request)
    {
        // Return kosong jika belum ada parameter bounds (untuk performa awal)
        if (!$request->has(['north', 'south', 'east', 'west'])) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        try {
            $n = $request->north;
            $s = $request->south;
            $e = $request->east;
            $w = $request->west;

            // Buat Polygon WKT (Urutan X Y bebas karena SRID 0)
            $polygonWKT = sprintf(
                "POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))",
                $w, $s, $e, $s, $e, $n, $w, $n, $w, $s
            );

            // Query Spatial: ST_Intersects dengan ST_GeomFromText Polos (SRID 0)
            $data = SpatialFeature::select(
                'id', 
                'name', 
                'properties',
                DB::raw('ST_AsGeoJSON(geom) as geometry') 
            )
            ->whereRaw("ST_Intersects(geom, ST_GeomFromText(?))", [$polygonWKT])
            ->limit(3000) // Batas data agar browser kuat
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

    /**
     * 4. IMPORT SHP (Support AJAX & Logging Detail)
     */
    public function storeShp(Request $request)
    {
        // Config Server untuk proses berat
        set_time_limit(0); 
        ini_set('memory_limit', '-1'); 
        ini_set('max_execution_time', 0);

        // Validasi
        $request->validate([
            'shp_files' => 'required',
            'shp_files.*' => 'file|mimes:zip|max:102400' // Max 100MB per file
        ]);

        $files = $request->file('shp_files');
        // Pastikan jadi array meskipun cuma 1 file
        if (!is_array($files)) {
            $files = [$files];
        }

        $successCount = 0;
        $failedInfo = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $extractPath = null;

            try {
                // 1. Buat folder temp unik
                $uniqueId = uniqid('shp_', true);
                $extractPath = storage_path('app/temp_shp/' . $uniqueId);
                
                if (!file_exists($extractPath)) mkdir($extractPath, 0777, true);

                // 2. Simpan & Ekstrak ZIP
                $file->storeAs('temp_shp/' . $uniqueId, 'source.zip');
                
                $zip = new ZipArchive;
                if ($zip->open(storage_path('app/temp_shp/' . $uniqueId . '/source.zip')) === TRUE) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                } else {
                    throw new \Exception('Gagal ekstrak ZIP.');
                }

                // 3. Cari file .shp (Recursive search)
                $shpFiles = glob($extractPath . '/**/*.shp'); 
                if (empty($shpFiles)) $shpFiles = glob($extractPath . '/*.shp');

                if (empty($shpFiles)) throw new \Exception('File .shp tidak ditemukan.');
                
                $shpFile = $shpFiles[0];

                // 4. Convert ke GeoJSON (Paksa output EPSG:4326 Lat/Long)
                $geojsonFile = $extractPath . '/output.json';
                
                // Tambahkan 2>&1 untuk menangkap output error dari CMD
                $cmd = "ogr2ogr -f GeoJSON -t_srs EPSG:4326 \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                $output = [];
                $returnVar = 0;
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile) || filesize($geojsonFile) == 0) {
                    $errorLog = implode("\n", $output);
                    Log::error("OGR2OGR Fail ($originalName): " . $errorLog);
                    
                    if (str_contains($errorLog, 'is not recognized')) {
                        throw new \Exception('Server tidak mengenali perintah "ogr2ogr". Cek installasi GDAL.');
                    }
                    throw new \Exception('Gagal Konversi SHP. Cek Log Laravel.');
                }

                // 5. Insert ke Database (SRID 0)
                $jsonContent = file_get_contents($geojsonFile);
                $geoData = json_decode($jsonContent, true);

                if (!isset($geoData['features'])) throw new \Exception('Format GeoJSON invalid.');

                // Transaction per file
                DB::transaction(function () use ($geoData) {
                    foreach ($geoData['features'] as $feature) {
                        $geom = json_encode($feature['geometry']);
                        $props = $feature['properties'] ?? [];
                        $name = $props['nama'] ?? $props['NAME'] ?? $props['NIB'] ?? 'Import';
                        
                        DB::table('spatial_features')->insert([
                            'name' => $name,
                            'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                            // PENTING: Gunakan ST_GeomFromGeoJSON tanpa SRID tambahan -> Hasilnya SRID 0
                            'geom' => DB::raw("ST_GeomFromGeoJSON('$geom')"), 
                            'created_at' => now(), 
                            'updated_at' => now()
                        ]);
                    }
                });

                $successCount++;
                
                // Bersihkan folder temp
                $this->deleteDirectory($extractPath);

            } catch (\Exception $e) {
                $failedInfo[] = $originalName . ": " . $e->getMessage();
                Log::error("Import Gagal $originalName: " . $e->getMessage());
                // Tetap bersihkan folder meski gagal
                if ($extractPath) $this->deleteDirectory($extractPath);
            }
        }

        // RESPON JSON UNTUK JAVASCRIPT
        if ($request->ajax() || $request->wantsJson()) {
            if (count($failedInfo) > 0) {
                return response()->json([
                    'status' => 'partial_error',
                    'message' => 'Gagal: ' . implode(', ', $failedInfo)
                ], 422);
            }
            return response()->json(['status' => 'success', 'message' => 'Berhasil']);
        }

        // Fallback Form Biasa
        if (count($failedInfo) > 0) return back()->with('error', implode(', ', $failedInfo));
        return back()->with('success', "Berhasil import $successCount file!");
    }

    // Helper hapus folder
    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    // --- SIMPAN MANUAL ---
    public function storeDraw(Request $request)
    {
        $request->validate(['name' => 'required', 'type' => 'required', 'geometry' => 'required']);
        try {
            DB::table('spatial_features')->insert([
                'name' => $request->name,
                'properties' => json_encode(['type' => $request->type, 'description' => $request->description, 'color' => '#ff0000']),
                'geom' => DB::raw("ST_GeomFromGeoJSON('{$request->geometry}')"),
                'created_at' => now(), 'updated_at' => now()
            ]);
            return response()->json(['status' => 'success', 'message' => 'Data disimpan!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}