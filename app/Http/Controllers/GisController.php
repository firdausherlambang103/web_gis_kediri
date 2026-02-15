<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use App\Models\Layer; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class GisController extends Controller
{
    // --- Helper 1: Keyword Pencarian ---
    private function getHakKeywords($kode) {
        if (!$kode) return [];
        $kode = strtoupper($kode);
        $keywords = [$kode]; 
        if ($kode == 'HM') { $keywords[] = 'Hak Milik'; $keywords[] = 'Milik'; }
        if ($kode == 'HGB') { $keywords[] = 'Hak Guna Bangunan'; $keywords[] = 'Guna Bangunan'; }
        if ($kode == 'HP') { $keywords[] = 'Hak Pakai'; $keywords[] = 'Pakai'; }
        if ($kode == 'WAKAF') { $keywords[] = 'Wakaf'; }
        if ($kode == 'KOSONG' || $kode == 'TANPA HAK') { $keywords[] = 'Tanah Negara'; $keywords[] = 'Belum Ada Hak'; $keywords[] = 'null'; }
        return $keywords;
    }

    // --- Helper 2: Deteksi Warna Otomatis ---
    private function getHakColor($tipeHak) {
        $tipe = strtoupper($tipeHak ?? '');
        if (str_contains($tipe, 'HM') || str_contains($tipe, 'MILIK')) return '#28a745';      
        if (str_contains($tipe, 'HGB') || str_contains($tipe, 'GUNA BANGUNAN')) return '#ffc107'; 
        if (str_contains($tipe, 'HP') || str_contains($tipe, 'PAKAI')) return '#17a2b8';      
        if (str_contains($tipe, 'WAKAF')) return '#ffffff';    
        if (str_contains($tipe, 'HPL') || str_contains($tipe, 'PENGELOLAAN')) return '#6f42c1';   
        return '#6c757d'; 
    }

    // --- Page & API ---
    public function index(Request $request)
    {
        $layers = Layer::where('is_active', true)->get();
        return view('admin.map', [
            'lat' => $request->input('lat'), 'lng' => $request->input('lng'),
            'search' => $request->input('search'), 'hak' => $request->input('hak'), 'layers' => $layers
        ]);
    }

    public function apiData(Request $request)
    {
        // Validasi parameter viewport peta
        if (!$request->has(['north', 'south', 'east', 'west', 'zoom'])) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }
        
        try {
            $n = $request->north; $s = $request->south; $e = $request->east; $w = $request->west;
            $zoom = (int) $request->zoom;
            $search = $request->input('search');
            $hak = $request->input('hak');
            $layerIds = $request->input('layers'); 

            // Membuat kotak area (Bounding Box) untuk query spatial
            $polygonWKT = sprintf("SRID=4326;POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))", $w, $s, $e, $s, $e, $n, $w, $n, $w, $s);
            $features = [];
            $strategy = '';

            // MODE CLUSTER (Zoom Jauh) - Menampilkan titik saja agar ringan
            if ($zoom < 14 && empty($search) && empty($hak) && empty($layerIds)) {
                $strategy = 'cluster';
                $gridSize = $zoom < 10 ? 0.05 : 0.005;
                $gridSizeStr = number_format($gridSize, 5, '.', ''); 
                
                // Gunakan && operator untuk memanfaatkan Spatial Index
                $clusters = DB::table('spatial_features')
                    ->select(DB::raw("COUNT(id) as total"), DB::raw("ST_AsGeoJSON(ST_Centroid(ST_Collect(geom::geometry))) as center"))
                    ->whereRaw("geom && ST_GeomFromEWKT(?)", [$polygonWKT]) 
                    ->groupByRaw("ST_SnapToGrid(ST_Centroid(geom::geometry), $gridSizeStr)")
                    ->get();

                foreach ($clusters as $cluster) {
                    if (!$cluster->center) continue;
                    $features[] = [
                        'type' => 'Feature', 'geometry' => json_decode($cluster->center),
                        'properties' => ['type' => 'cluster', 'count' => $cluster->total]
                    ];
                }
            } 
            // MODE DETAIL (Zoom Dekat) - Menampilkan geometri asli/disederhanakan
            else {
                $query = SpatialFeature::query()->with('layer')->whereRaw("geom && ST_GeomFromEWKT(?)", [$polygonWKT]);
                
                if (!empty($layerIds) && is_array($layerIds)) $query->whereIn('layer_id', $layerIds);
                if ($search) {
                    $term = '%' . $search . '%';
                    $query->where(function($q) use ($term) { $q->where('name', 'ILIKE', $term)->orWhereRaw("properties::text ILIKE ?", [$term]); });
                }
                if ($hak) {
                    $keywords = $this->getHakKeywords($hak);
                    $query->where(function($q) use ($keywords) { foreach ($keywords as $word) $q->orWhereRaw("properties::text ILIKE ?", ['%' . $word . '%']); });
                }

                // Simplifikasi geometri jika zoom belum terlalu dekat untuk performa
                $selectGeom = ($zoom > 16 || !empty($search) || !empty($hak)) 
                    ? "ST_AsGeoJSON(geom)" 
                    : "ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom::geometry, 0.00005))";
                $strategy = ($zoom > 16 || !empty($search) || !empty($hak)) ? 'detail' : 'simplified';

                $data = $query->select('id', 'name', 'properties', 'layer_id', DB::raw("$selectGeom as geometry"))->limit(3000)->get();
                
                foreach ($data as $item) {
                    if (!$item->geometry) continue;
                    $props = $item->properties ?? [];
                    
                    // Tentukan warna berdasarkan Layer atau Hak
                    $finalColor = $item->layer ? ($item->layer->mode === 'auto_hak' ? $this->getHakColor($props['raw_data']['TIPEHAK'] ?? '') : $item->layer->color) : '#3388ff';
                    $props['layer_color'] = $finalColor; 

                    $features[] = [
                        'type' => 'Feature', 'geometry' => json_decode($item->geometry), 
                        'properties' => array_merge(['id'=>$item->id, 'name'=>$item->name], $props)
                    ];
                }
            }
            return response()->json(['type'=>'FeatureCollection', 'features'=>$features, 'strategy'=>$strategy]);
        } catch (\Exception $e) { return response()->json(['error'=>$e->getMessage()], 500); }
    }

    // --- IMPORT SHP (VERSI STABIL: UNLIMITED MEMORY & TIME) ---
    public function storeShp(Request $request)
    {
        Log::info('--- MEMULAI UPLOAD SHP ---'); 

        // 1. SETTING PHP EKSTREM (Wajib untuk 1 Juta Data)
        set_time_limit(0);           // Waktu eksekusi tidak terbatas
        ini_set('memory_limit', '-1'); // Gunakan semua RAM yang tersedia
        ini_set('max_execution_time', 0);
        DB::disableQueryLog();       // Matikan pencatatan query Laravel agar hemat RAM

        $request->validate([
            'shp_files' => 'required', 
            // Validasi ukuran di sini hanya formalitas, server (Nginx/PHP) yang memegang kendali utama
            'shp_files.*' => 'file', 
            'layer_id' => 'nullable|exists:layers,id'
        ]);
        
        $files = $request->file('shp_files');
        if (!is_array($files)) $files = [$files];
        $layerId = $request->layer_id;

        $insertedCount = 0; $updatedCount = 0; $failedInfo = [];

        // --- DETEKSI OS & PATH GDAL USER ---
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $envPrefix = "";
        $ogr2ogrCmd = "ogr2ogr"; 

        if ($isWindows) {
            // Path spesifik user MasterD
            $userOsGeoPath = 'C:\\Users\\MasterD\\AppData\\Local\\Programs\\OSGeo4W';
            
            if (is_dir($userOsGeoPath)) {
                $projLibPath = $userOsGeoPath . '\\share\\proj';
                if (file_exists($projLibPath . '\\proj.db')) $envPrefix = "set \"PROJ_LIB={$projLibPath}\" && ";
                
                $exePath = $userOsGeoPath . '\\bin\\ogr2ogr.exe';
                if (file_exists($exePath)) $ogr2ogrCmd = '"' . $exePath . '"'; 
            } else {
                // Fallback ke Environment Variable
                $projLibPath = env('GDAL_PROJ_LIB');
                if ($projLibPath) $envPrefix = "set \"PROJ_LIB={$projLibPath}\" && ";
            }
        }

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            Log::info("Memproses file: " . $originalName); 

            $uniqueId = uniqid('shp_', true);
            $extractPath = storage_path('app/temp_shp/' . $uniqueId);
            
            try {
                if (!file_exists($extractPath)) mkdir($extractPath, 0777, true);
                
                $zip = new ZipArchive;
                if ($zip->open($file->getPathname()) === TRUE) { 
                    $zip->extractTo($extractPath); 
                    $zip->close(); 
                } else { 
                    throw new \Exception('Gagal ekstrak ZIP. Pastikan file valid.'); 
                }

                $shpFiles = [];
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath));
                foreach ($iterator as $info) {
                    if ($info->isFile() && strtolower($info->getExtension()) === 'shp') {
                        $shpFiles[] = $info->getPathname();
                    }
                }
                if (empty($shpFiles)) throw new \Exception('File .shp tidak ditemukan dalam ZIP.');
                
                $shpFile = $shpFiles[0];
                $geojsonFile = $extractPath . '/output.json';
                
                // Konversi SHP -> GeoJSON menggunakan GDAL
                $cmd = $isWindows 
                    ? "{$envPrefix}{$ogr2ogrCmd} -f GeoJSON -dim XY -t_srs EPSG:4326 -skipfailures \"{$geojsonFile}\" \"{$shpFile}\" 2>&1"
                    : "ogr2ogr -f GeoJSON -dim XY -t_srs EPSG:4326 -skipfailures \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                
                $output = [];
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile) || filesize($geojsonFile) < 10) {
                    throw new \Exception("Gagal konversi GDAL. Cek Log.");
                }

                // 2. BACA GEOJSON (Chunking manual jika bisa, atau decode full jika memory cukup)
                // Karena kita sudah set memory_limit -1, json_decode aman untuk file < 500MB
                $jsonContent = file_get_contents($geojsonFile);
                $geoData = json_decode($jsonContent, true);
                unset($jsonContent); // Bebaskan RAM string JSON
                
                if (!isset($geoData['features'])) throw new \Exception('JSON hasil konversi invalid.');

                // 3. INDEXING DATA LAMA (Untuk Update Cepat)
                // Hanya ambil kolom ID dan NIB untuk menghemat memori
                $existingMap = DB::table('spatial_features')
                    ->where('layer_id', $layerId)
                    ->select('id', DB::raw("properties->'raw_data'->>'NIB' as nib"))
                    ->pluck('id', 'nib')
                    ->toArray();

                // 4. PROSES DALAM BATCH
                $batchSize = 250; // Ukuran batch aman
                $chunks = array_chunk($geoData['features'], $batchSize);
                unset($geoData); // Bebaskan RAM array besar
                gc_collect_cycles(); // Paksa Garbage Collector

                foreach ($chunks as $chunk) {
                    $insertData = [];
                    $updateIds = []; // Simpan ID untuk update manual satu-satu (lebih aman daripada complex query)

                    foreach ($chunk as $feature) {
                        if (!isset($feature['geometry'])) continue;

                        $props = $feature['properties'] ?? [];
                        $nib = isset($props['NIB']) ? (string)$props['NIB'] : null;
                        
                        $name = $nib ?? ($props['ID'] ?? 'Aset Import');
                        $geomJson = json_encode($feature['geometry']);
                        $now = now()->toDateTimeString();

                        // Cek Duplikat
                        if ($nib && isset($existingMap[$nib])) {
                            // Update
                            DB::table('spatial_features')->where('id', $existingMap[$nib])->update([
                                'name' => $name,
                                'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                                'geom' => DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('$geomJson'), 4326))"),
                                'updated_at' => $now
                            ]);
                            $updatedCount++;
                        } else {
                            // Insert
                            $insertData[] = [
                                'name' => $name,
                                'layer_id' => $layerId,
                                'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                                'geom' => DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('$geomJson'), 4326))"),
                                'created_at' => $now,
                                'updated_at' => $now
                            ];
                        }
                    }

                    // Eksekusi Bulk Insert
                    if (!empty($insertData)) {
                        DB::table('spatial_features')->insert($insertData);
                        $insertedCount += count($insertData);
                    }
                    
                    unset($insertData);
                    gc_collect_cycles();
                }

                Log::info("Sukses file: " . $originalName . ". Insert: $insertedCount, Update: $updatedCount");

            } catch (\Exception $e) {
                Log::error("Error file " . $originalName . ": " . $e->getMessage());
                $failedInfo[] = "$originalName: " . $e->getMessage();
            } finally {
                $this->deleteDirectory($extractPath);
            }
        }

        if ($request->ajax()) {
            if (count($failedInfo) > 0) return response()->json(['status' => 'partial_error', 'message' => implode(' | ', $failedInfo)], 422);
            return response()->json(['status' => 'success', 'message' => "Selesai! Baru: $insertedCount, Update: $updatedCount"]);
        }
        return back()->with('success', "Proses Selesai! (Baru: $insertedCount, Update: $updatedCount)");
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

    // --- FITUR LAIN (TIDAK BERUBAH) ---
    public function storeDraw(Request $request)
    {
        try {
            $request->validate(['name' => 'required', 'geometry' => 'required', 'color' => 'required', 'status' => 'required']);
            $geometryJson = $request->geometry;
            $sqlLuas = "SELECT ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) as luas_m2";
            $luasResult = DB::selectOne($sqlLuas, [$geometryJson]);
            $luas = $luasResult->luas_m2 ?? 0;
            $layerId = $request->input('layer_id'); 

            DB::table('spatial_features')->insert([
                'name' => $request->name,
                'layer_id' => $layerId,
                'properties' => json_encode([
                    'type' => 'Manual',
                    'raw_data' => [
                        'TIPEHAK' => $request->status, 'KECAMATAN' => $request->kecamatan ?? '-', 'KELURAHAN' => $request->desa ?? '-',
                        'LUASTERTUL' => round($luas, 2), 'PENGGUNAAN' => $request->description
                    ],
                    'color' => $request->color, 'description' => $request->description
                ]),
                'geom' => DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('$geometryJson'), 4326))"),
                'created_at' => now(), 'updated_at' => now()
            ]);
            return response()->json(['status' => 'success', 'message' => 'Data berhasil disimpan! Luas: ' . round($luas, 2) . ' m²']);
        } catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    public function storeLayer(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'color' => 'required|string|max:7']);
        $layer = Layer::create([
            'name' => $request->name, 'color' => $request->color, 'description' => $request->description,
            'mode' => $request->mode ?? 'standard', 'is_active' => true
        ]);
        return response()->json(['status' => 'success', 'data' => $layer]);
    }
    public function getLayers() { return response()->json(Layer::all()); }

    public function indexTable(Request $request)
    {
        $search = $request->input('search'); 
        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa'); 
        $hak = $request->input('hak'); 
        $sumber = $request->input('sumber');
        $layerId = $request->input('layer_id'); 

        $query = SpatialFeature::query()->with('layer');
        if ($layerId) $query->where('layer_id', $layerId);
        if ($sumber == 'manual') $query->whereRaw("properties->>'type' = 'Manual'");
        elseif ($sumber == 'import') $query->whereRaw("properties->>'type' = 'Imported'");
        if ($search) { 
            $term = '%' . $search . '%'; 
            $query->where(function($q) use ($term) { $q->where('name', 'ILIKE', $term)->orWhereRaw("properties::text ILIKE ?", [$term]); }); 
        }
        if ($hak) { 
            $keywords = $this->getHakKeywords($hak); 
            $query->where(function($q) use ($keywords) { foreach ($keywords as $word) $q->orWhereRaw("properties::text ILIKE ?", ['%' . $word . '%']); }); 
        }
        if ($kecamatan) $query->whereRaw("properties::text ILIKE ?", ['%' . $kecamatan . '%']);
        if ($desa) $query->whereRaw("properties::text ILIKE ?", ['%' . $desa . '%']);

        $data = $query->select('id', 'name', 'properties', 'layer_id', 'created_at', DB::raw("ST_AsGeoJSON(ST_Centroid(geom::geometry)) as center"))
                      ->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $layers = Layer::orderBy('name', 'asc')->get();
        return view('admin.aset.index', compact('data', 'layers', 'search', 'kecamatan', 'desa', 'hak', 'sumber', 'layerId'));
    }

    public function show($id)
    {
        $item = DB::table('spatial_features')->where('id', $id)->first();
        if (!$item) return response()->json(['error' => 'Data tidak ditemukan'], 404);
        $props = json_decode($item->properties, true);
        return response()->json([
            'id' => $item->id, 'name' => $item->name,
            'status' => $props['raw_data']['TIPEHAK'] ?? $props['raw_data']['TIPE_HAK'] ?? '-',
            'kecamatan' => $props['raw_data']['KECAMATAN'] ?? '-', 'desa' => $props['raw_data']['KELURAHAN'] ?? $props['raw_data']['DESA'] ?? '-',
            'luas' => $props['raw_data']['LUASTERTUL'] ?? $props['raw_data']['LUAS'] ?? 0, 'description' => $props['description'] ?? $props['raw_data']['PENGGUNAAN'] ?? '',
            'color' => $props['color'] ?? '#ff0000', 'layer_id' => $item->layer_id
        ]);
    }

    public function update(Request $request, $id) {
        try {
            $item = DB::table('spatial_features')->where('id', $id)->first();
            if (!$item) return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
            $props = json_decode($item->properties, true) ?? [];
            $props['raw_data']['TIPEHAK'] = $request->status;
            $props['raw_data']['KECAMATAN'] = $request->kecamatan;
            $props['raw_data']['KELURAHAN'] = $request->desa;
            $props['raw_data']['PENGGUNAAN'] = $request->description;
            if($request->has('luas')) $props['raw_data']['LUASTERTUL'] = $request->luas;
            $props['color'] = $request->color; $props['description'] = $request->description;
            DB::table('spatial_features')->where('id', $id)->update(['name' => $request->name, 'properties' => json_encode($props), 'updated_at' => now()]);
            return response()->json(['status' => 'success', 'message' => 'Data berhasil diperbarui!']);
        } catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    public function destroy($id) {
        try { DB::table('spatial_features')->delete($id); return response()->json(['status' => 'success', 'message' => 'Data berhasil dihapus!']); } 
        catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }
}