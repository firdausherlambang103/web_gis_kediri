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
use App\Helpers\LogHelper; // <--- PENTING: Import Helper Log

class GisController extends Controller
{
    // =========================================================================
    // HELPER FUNCTIONS
    // =========================================================================

    // Helper: Keyword Pencarian Berdasarkan Tipe Hak
    private function getHakKeywords($kode) {
        if (!$kode) return [];
        $kode = strtoupper($kode);
        $keywords = [$kode]; 
        if ($kode == 'HM') { $keywords[] = 'Hak Milik'; $keywords[] = 'Milik'; }
        if ($kode == 'HGB') { $keywords[] = 'Hak Guna Bangunan'; $keywords[] = 'Guna Bangunan'; }
        if ($kode == 'HGU') { $keywords[] = 'Hak Guna Usaha'; $keywords[] = 'Guna Usaha'; } 
        if ($kode == 'HP') { $keywords[] = 'Hak Pakai'; $keywords[] = 'Pakai'; }
        if ($kode == 'WAKAF') { $keywords[] = 'Wakaf'; }
        if ($kode == 'KOSONG' || $kode == 'TANPA HAK') { $keywords[] = 'Tanah Negara'; $keywords[] = 'Belum Ada Hak'; $keywords[] = 'null'; }
        return $keywords;
    }

    // Helper: Deteksi Warna Otomatis (Fallback jika database layer null)
    private function getHakColor($tipeHak) {
        $tipe = strtoupper($tipeHak ?? '');
        if (str_contains($tipe, 'HM') || str_contains($tipe, 'MILIK')) return '#28a745';      
        if (str_contains($tipe, 'HGB') || str_contains($tipe, 'GUNA BANGUNAN')) return '#ffc107'; 
        if (str_contains($tipe, 'HGU') || str_contains($tipe, 'GUNA USAHA')) return '#fd7e14'; 
        if (str_contains($tipe, 'HP') || str_contains($tipe, 'PAKAI')) return '#17a2b8';      
        if (str_contains($tipe, 'WAKAF')) return '#ffffff';    
        if (str_contains($tipe, 'HPL') || str_contains($tipe, 'PENGELOLAAN')) return '#6f42c1';   
        return '#6c757d'; 
    }

    // Helper: Hapus Folder Temporary
    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    // =========================================================================
    // VIEW & API METHODS
    // =========================================================================

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
        if (!$request->has(['north', 'south', 'east', 'west', 'zoom'])) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }
        
        try {
            $n = $request->north; $s = $request->south; $e = $request->east; $w = $request->west;
            $zoom = (int) $request->zoom;
            $search = $request->input('search');
            $hak = $request->input('hak');
            $layerIds = $request->input('layers'); 

            $polygonWKT = sprintf("SRID=4326;POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))", $w, $s, $e, $s, $e, $n, $w, $n, $w, $s);
            $features = [];
            $strategy = '';

            // MODE CLUSTER (Zoom Jauh)
            if ($zoom < 14 && empty($search) && empty($hak) && empty($layerIds)) {
                $strategy = 'cluster';
                $gridSize = $zoom < 10 ? 0.05 : 0.005;
                $gridSizeStr = number_format($gridSize, 5, '.', ''); 
                
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
            // MODE DETAIL (Zoom Dekat)
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

                $selectGeom = ($zoom > 16 || !empty($search) || !empty($hak)) 
                    ? "ST_AsGeoJSON(geom)" 
                    : "ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom::geometry, 0.00005))";
                $strategy = ($zoom > 16 || !empty($search) || !empty($hak)) ? 'detail' : 'simplified';

                $data = $query->select('id', 'name', 'properties', 'layer_id', 'file_path', DB::raw("$selectGeom as geometry"))->limit(3000)->get();
                
                foreach ($data as $item) {
                    if (!$item->geometry) continue;
                    $props = $item->properties ?? [];
                    
                    $finalColor = '#3388ff';
                    
                    if ($item->layer) {
                        // CEK LOGIKA WARNA DINAMIS
                        if (($item->layer->mode ?? 'standard') === 'auto_hak') {
                            
                            $tipeHak = strtoupper($props['raw_data']['TIPEHAK'] ?? $props['raw_data']['TIPE_HAK'] ?? '');
                            
                            // Ambil warna dari settingan database layer
                            if (str_contains($tipeHak, 'HM') || str_contains($tipeHak, 'MILIK')) {
                                $finalColor = $item->layer->color_hm ?? '#28a745';
                            } elseif (str_contains($tipeHak, 'HGB') || str_contains($tipeHak, 'GUNA BANGUNAN')) {
                                $finalColor = $item->layer->color_hgb ?? '#ffc107';
                            } elseif (str_contains($tipeHak, 'HGU') || str_contains($tipeHak, 'GUNA USAHA')) { 
                                $finalColor = $item->layer->color_hgu ?? '#fd7e14';
                            } elseif (str_contains($tipeHak, 'HP') || str_contains($tipeHak, 'PAKAI')) {
                                $finalColor = $item->layer->color_hp ?? '#17a2b8';
                            } elseif (str_contains($tipeHak, 'WAKAF')) {
                                $finalColor = $item->layer->color_wakaf ?? '#6f42c1';
                            } else {
                                $finalColor = $item->layer->color_tn ?? '#6c757d';
                            }
                        } else {
                            $finalColor = $item->layer->color;
                        }
                    }
                    $props['layer_color'] = $finalColor; 

                    $features[] = [
                        'type' => 'Feature', 'geometry' => json_decode($item->geometry), 
                        'properties' => array_merge(['id'=>$item->id, 'name'=>$item->name, 'file_path'=>$item->file_path], $props)
                    ];
                }
            }
            return response()->json(['type'=>'FeatureCollection', 'features'=>$features, 'strategy'=>$strategy]);
        } catch (\Exception $e) { return response()->json(['error'=>$e->getMessage()], 500); }
    }

    // =========================================================================
    // UPLOAD LOGIC
    // =========================================================================

    public function storeShp(Request $request)
    {
        Log::info('--- MEMULAI UPLOAD SHP ---'); 

        set_time_limit(0);              
        ini_set('memory_limit', '1024M'); 
        ini_set('max_execution_time', 0);
        DB::disableQueryLog();          

        $request->validate([
            'shp_files' => 'required', 
            'shp_files.*' => 'file', 
            'layer_id' => 'nullable|exists:layers,id'
        ]);
        
        $files = $request->file('shp_files');
        if (!is_array($files)) $files = [$files];
        $layerId = $request->layer_id;

        $stats = [
            'processed' => 0, 'inserted' => 0, 
            'updated' => 0, 'skipped' => 0
        ];
        $failedInfo = [];

        // Deteksi Path GDAL
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $envPrefix = "";
        $ogr2ogrCmd = "ogr2ogr"; 

        if ($isWindows) {
            $userOsGeoPath = 'C:\\Users\\MasterD\\AppData\\Local\\Programs\\OSGeo4W';
            if (is_dir($userOsGeoPath)) {
                $projLibPath = $userOsGeoPath . '\\share\\proj';
                if (file_exists($projLibPath . '\\proj.db')) $envPrefix = "set \"PROJ_LIB={$projLibPath}\" && ";
                $exePath = $userOsGeoPath . '\\bin\\ogr2ogr.exe';
                if (file_exists($exePath)) $ogr2ogrCmd = '"' . $exePath . '"'; 
            } else {
                $projLibPath = env('GDAL_PROJ_LIB');
                if ($projLibPath) $envPrefix = "set \"PROJ_LIB={$projLibPath}\" && ";
            }
        }

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $uniqueId = uniqid('shp_', true);
            $extractPath = storage_path('app/temp_shp/' . $uniqueId);
            
            try {
                if (!file_exists($extractPath)) mkdir($extractPath, 0777, true);
                
                // Ekstrak ZIP
                $zip = new ZipArchive;
                if ($zip->open($file->getPathname()) === TRUE) { 
                    $zip->extractTo($extractPath); 
                    $zip->close(); 
                } else { 
                    throw new \Exception('Gagal ekstrak ZIP.'); 
                }

                // Cari File .shp
                $shpFiles = [];
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath));
                foreach ($iterator as $info) {
                    if ($info->isFile() && strtolower($info->getExtension()) === 'shp') {
                        $shpFiles[] = $info->getPathname();
                    }
                }
                if (empty($shpFiles)) throw new \Exception('File .shp tidak ditemukan.');
                
                $shpFile = $shpFiles[0];
                $geojsonFile = $extractPath . '/output.json';
                
                // Konversi SHP ke GeoJSONSeq
                $cmd = $isWindows 
                    ? "{$envPrefix}{$ogr2ogrCmd} -f GeoJSONSeq -dim XY -t_srs EPSG:4326 -skipfailures \"{$geojsonFile}\" \"{$shpFile}\" 2>&1"
                    : "ogr2ogr -f GeoJSONSeq -dim XY -t_srs EPSG:4326 -skipfailures \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                
                $output = [];
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile) || filesize($geojsonFile) < 10) {
                    $cmd = str_replace('GeoJSONSeq', 'GeoJSON', $cmd);
                    exec($cmd, $output, $returnVar);
                    if (!file_exists($geojsonFile) || filesize($geojsonFile) < 10) throw new \Exception("Gagal konversi GDAL.");
                }

                $handle = fopen($geojsonFile, "r");
                if (!$handle) throw new \Exception("Gagal membuka hasil konversi.");

                // Load Index DB (Hanya untuk Cek Update jika Mode SUBSEQUENT)
                $existingMap = DB::table('spatial_features')
                    ->where('layer_id', $layerId)
                    ->select('id', DB::raw("properties->'raw_data'->>'NIB' as nib"), DB::raw("properties->'raw_data'->>'ID' as raw_id"))
                    ->get()
                    ->mapWithKeys(function ($item) {
                        $key = $item->nib ?: ($item->raw_id ?: null);
                        return $key ? [(string)$key => $item->id] : [];
                    })
                    ->toArray();

                $kecamatanModes = []; // Cache Status Kecamatan
                $localProcessed = []; // Cache Duplikat Internal

                $batchData = [];
                $batchSize = 500; 
                
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    $line = rtrim($line, ',');
                    if ($line == '[' || $line == ']' || $line == '{' || $line == '}' || substr($line, 0, 10) == '"type":') continue;

                    $feature = json_decode($line, true);
                    if (!$feature) continue;
                    
                    $stats['processed']++; 

                    if (!isset($feature['geometry']) || empty($feature['geometry'])) {
                        $stats['skipped']++; continue;
                    }

                    $props = $feature['properties'] ?? [];
                    $nib = isset($props['NIB']) ? (string)$props['NIB'] : null;
                    $checkId = isset($props['ID']) ? (string)$props['ID'] : null;
                    $kecamatan = isset($props['KECAMATAN']) ? strtoupper(trim($props['KECAMATAN'])) : 'UNKNOWN';
                    
                    $name = $nib ?? ($checkId ?? 'Aset Import');
                    $geomJson = json_encode($feature['geometry']);
                    if ($geomJson == 'null' || !$geomJson) { $stats['skipped']++; continue; }

                    $now = now()->toDateTimeString();
                    $uniqueKey = $nib ?: $checkId;

                    // --- CEK MODE KECAMATAN ---
                    if (!isset($kecamatanModes[$kecamatan])) {
                        $existsInDb = DB::table('spatial_features')
                            ->where('layer_id', $layerId)
                            ->whereRaw("properties->'raw_data'->>'KECAMATAN' ILIKE ?", [$kecamatan])
                            ->exists();
                        
                        $kecamatanModes[$kecamatan] = $existsInDb ? 'SUBSEQUENT' : 'FIRST';
                    }
                    $mode = $kecamatanModes[$kecamatan];

                    // --- LOGIKA UTAMA ---
                    if ($uniqueKey) {
                        if ($mode === 'SUBSEQUENT') {
                            if (isset($localProcessed[$uniqueKey])) {
                                $stats['skipped']++; 
                                continue; 
                            }
                            $localProcessed[$uniqueKey] = true;

                            if (isset($existingMap[$uniqueKey])) {
                                DB::table('spatial_features')
                                    ->where('id', $existingMap[$uniqueKey])
                                    ->update([
                                        'name' => $name,
                                        'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                                        'geom' => DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('$geomJson'), 4326))"),
                                        'updated_at' => $now
                                    ]);
                                $stats['updated']++;
                                continue; 
                            }
                        }
                    }

                    // --- INSERT ---
                    $batchData[] = [
                        'name' => $name,
                        'layer_id' => $layerId,
                        'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                        'geom' => DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('$geomJson'), 4326))"),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                    $stats['inserted']++;

                    if (count($batchData) >= $batchSize) {
                        DB::table('spatial_features')->insert($batchData);
                        $batchData = []; 
                        gc_collect_cycles();
                    }
                }

                if (!empty($batchData)) {
                    DB::table('spatial_features')->insert($batchData);
                }

                fclose($handle);
                
                // --- LOGGING AKTIVITAS UPLOAD ---
                LogHelper::record('UPLOAD', $originalName, "Upload SHP selesai. Baru: {$stats['inserted']}, Update: {$stats['updated']}");

            } catch (\Exception $e) {
                Log::error("Error file " . $originalName . ": " . $e->getMessage());
                $failedInfo[] = "$originalName: " . $e->getMessage();
            } finally {
                $this->deleteDirectory($extractPath);
            }
        }

        if ($request->ajax()) {
            if (count($failedInfo) > 0) return response()->json(['status' => 'partial_error', 'message' => implode(' | ', $failedInfo)], 422);
            
            $msg = "Selesai! Total: {$stats['processed']}. Baru: {$stats['inserted']}. Update: {$stats['updated']}. Skip: {$stats['skipped']}.";
            return response()->json(['status' => 'success', 'message' => $msg]);
        }
        
        return back()->with('success', "Proses Selesai! (Lihat Log)");
    }

    // =========================================================================
    // MANUAL DRAW & CRUD METHODS
    // =========================================================================

    public function storeDraw(Request $request)
    {
        try {
            // 1. Validasi Input (Tambahkan validasi file)
            $request->validate([
                'name' => 'required',
                'geometry' => 'required',
                'color' => 'required',
                'status' => 'required',
                'document' => 'nullable|file|mimes:pdf|max:5120' // Maksimal 5MB, hanya PDF
            ]);

            $geometryJson = $request->geometry;
            
            // Hitung Luas
            $sqlLuas = "SELECT ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) as luas_m2";
            $luasResult = DB::selectOne($sqlLuas, [$geometryJson]);
            $luas = $luasResult->luas_m2 ?? 0;
            
            $layerId = $request->input('layer_id');
            
            // 2. PROSES UPLOAD FILE
            $filePath = null;
            if ($request->hasFile('document')) {
                // Simpan ke folder: storage/app/public/documents
                $file = $request->file('document');
                $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $filePath = $file->storeAs('documents', $filename, 'public'); 
            }

            // 3. Simpan ke Database
            DB::table('spatial_features')->insert([
                'name' => $request->name,
                'layer_id' => $layerId,
                'file_path' => $filePath, // <--- SIMPAN PATH FILE
                'properties' => json_encode([
                    'type' => 'Manual',
                    'raw_data' => [
                        'TIPEHAK' => $request->status, 
                        'KECAMATAN' => $request->kecamatan ?? '-', 
                        'KELURAHAN' => $request->desa ?? '-',
                        'LUASTERTUL' => round($luas, 2), 
                        'PENGGUNAAN' => $request->description
                    ],
                    'color' => $request->color, 
                    'description' => $request->description
                ]),
                'geom' => DB::raw("ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON('$geometryJson'), 4326))"),
                'created_at' => now(), 
                'updated_at' => now()
            ]);

            // --- LOGGING CREATE MANUAL ---
            LogHelper::record('CREATE', $request->name, "Menambah aset manual dengan dokumen PDF");

            return response()->json(['status' => 'success', 'message' => 'Data dan dokumen berhasil disimpan!']);
            
        } catch (\Exception $e) { 
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); 
        }
    }

    public function storeLayer(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'color' => 'required|string|max:7']);
        $layer = Layer::create([
            'name' => $request->name, 
            'color' => $request->color, 
            'description' => $request->description,
            'mode' => $request->mode ?? 'standard', 
            'is_active' => true
        ]);

        // --- LOGGING CREATE LAYER ---
        LogHelper::record('CREATE_LAYER', $request->name, "Membuat layer baru");

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
            'color' => $props['color'] ?? '#ff0000', 'layer_id' => $item->layer_id,
            'file_path' => $item->file_path // <--- Return File Path
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
            
            // --- LOGGING UPDATE ---
            LogHelper::record('UPDATE', $request->name, "Update data aset ID: $id");

            return response()->json(['status' => 'success', 'message' => 'Data berhasil diperbarui!']);
        } catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    public function destroy($id) {
        try { 
            // Ambil nama dulu untuk log
            $name = DB::table('spatial_features')->where('id', $id)->value('name') ?? "ID $id";
            
            DB::table('spatial_features')->delete($id); 
            
            // --- LOGGING DELETE ---
            LogHelper::record('DELETE', $name, "Menghapus data aset");

            return response()->json(['status' => 'success', 'message' => 'Data berhasil dihapus!']); 
        } 
        catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    // --- Update Warna Layer via Ajax ---
    public function updateLayerColor(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:layers,id',
            'color' => 'required|string|max:7'
        ]);

        Layer::where('id', $request->id)->update(['color' => $request->color]);

        // --- LOGGING UPDATE LAYER COLOR ---
        LogHelper::record('UPDATE_LAYER', "Layer ID " . $request->id, "Ubah warna layer ke " . $request->color);

        return response()->json(['status' => 'success', 'message' => 'Warna layer diperbarui']);
    }
}