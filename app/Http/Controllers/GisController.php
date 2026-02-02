<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class GisController extends Controller
{
    /**
     * Halaman Utama Peta
     * Menangkap parameter URL untuk fitur "Lihat di Peta" dari tabel
     */
    public function index(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $search = $request->input('search');
        $hak = $request->input('hak');

        return view('admin.map', compact('lat', 'lng', 'search', 'hak'));
    }

    /**
     * Halaman Tabel Data (Optimasi PostgreSQL + Full Search)
     */
    public function indexTable(Request $request)
    {
        $search = $request->input('search');
        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa');
        $hak = $request->input('hak');

        $query = SpatialFeature::query();

        // 1. Filter Pencarian Umum (Nama / NIB / Pemilik)
        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($term) {
                // ILIKE = Case Insensitive Like di PostgreSQL
                $q->where('name', 'ILIKE', $term)
                  // Casting properties (jsonb) ke text agar bisa dicari string-nya secara global
                  ->orWhereRaw("properties::text ILIKE ?", [$term]);
            });
        }

        // 2. Filter Tipe Hak (Cari di seluruh string JSON properties)
        if ($hak) {
            $term = '%' . strtolower($hak) . '%';
            $query->whereRaw("properties::text ILIKE ?", [$term]);
        }

        // 3. Filter Wilayah
        if ($kecamatan) {
            $term = '%' . strtolower($kecamatan) . '%';
            $query->whereRaw("properties::text ILIKE ?", [$term]);
        }
        if ($desa) {
            $term = '%' . strtolower($desa) . '%';
            $query->whereRaw("properties::text ILIKE ?", [$term]);
        }

        // Ambil data + Centroid untuk tombol "Lihat Lokasi"
        $data = $query->select(
            'id', 'name', 'properties', 'created_at',
            DB::raw("ST_AsGeoJSON(ST_Centroid(geom)) as center")
        )
        ->orderBy('id', 'desc')
        ->paginate(15)
        ->withQueryString();

        return view('admin.aset.index', compact('data', 'search', 'kecamatan', 'desa', 'hak'));
    }

    /**
     * API PETA (PostGIS Optimized + LOD + Full Search)
     */
    public function apiData(Request $request)
    {
        if (!$request->has(['north', 'south', 'east', 'west', 'zoom'])) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        try {
            $n = $request->north; $s = $request->south; $e = $request->east; $w = $request->west;
            $zoom = (int) $request->zoom;
            $search = $request->input('search');
            $filterHak = $request->input('hak');

            // SRID 4326 (WGS84) Wajib di PostGIS
            $polygonWKT = sprintf("SRID=4326;POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))", $w, $s, $e, $s, $e, $n, $w, $n, $w, $s);

            $features = [];
            $strategy = '';

            // --- STRATEGI LEVEL OF DETAIL (LOD) ---

            // 1. MODE CLUSTER (Zoom Jauh & Tanpa Filter)
            if ($zoom < 14 && empty($search) && empty($filterHak)) {
                $strategy = 'cluster';
                // ST_SnapToGrid: Cara native & tercepat clustering di PostGIS
                $gridSize = $zoom < 10 ? 0.05 : 0.005;

                $clusters = DB::table('spatial_features')
                    ->select(DB::raw("COUNT(*) as total, ST_AsGeoJSON(ST_Centroid(geom)) as center"))
                    ->whereRaw("ST_Intersects(geom, ST_GeomFromEWKT(?))", [$polygonWKT])
                    ->groupByRaw("ST_SnapToGrid(ST_Centroid(geom), ?)", [$gridSize])
                    ->get();

                foreach ($clusters as $cluster) {
                    if (!$cluster->center) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => json_decode($cluster->center),
                        'properties' => ['type' => 'cluster', 'count' => $cluster->total]
                    ];
                }
            } 
            // 2. MODE DETAIL (Zoom Dekat / Ada Filter)
            else {
                $query = SpatialFeature::query()
                    ->whereRaw("ST_Intersects(geom, ST_GeomFromEWKT(?))", [$polygonWKT]);
                
                // Filter Search
                if ($search) {
                    $term = '%' . strtolower($search) . '%';
                    $query->where(function($q) use ($term) {
                        $q->where('name', 'ILIKE', $term)
                          ->orWhereRaw("properties::text ILIKE ?", [$term]);
                    });
                }

                // Filter Hak
                if ($filterHak) {
                    $term = '%' . strtolower($filterHak) . '%';
                    $query->whereRaw("properties::text ILIKE ?", [$term]);
                }

                // Simplifikasi Geometri
                // ST_SimplifyPreserveTopology mencegah polygon "bolong" saat zoom out
                $selectGeom = ($zoom > 16 || !empty($search) || !empty($filterHak)) 
                    ? "ST_AsGeoJSON(geom)" 
                    : "ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.00005))";
                
                $strategy = ($zoom > 16 || !empty($search) || !empty($filterHak)) ? 'detail' : 'simplified';

                $data = $query->select('id', 'name', 'properties', DB::raw("$selectGeom as geometry"))
                              ->limit(3000)
                              ->get();

                foreach ($data as $item) {
                    if (!$item->geometry) continue;
                    $features[] = [
                        'type' => 'Feature', 
                        'geometry' => json_decode($item->geometry), 
                        'properties' => array_merge(['id'=>$item->id, 'name'=>$item->name], $item->properties??[])
                    ];
                }
            }

            return response()->json(['type'=>'FeatureCollection', 'features'=>$features, 'strategy'=>$strategy]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error'=>$e->getMessage()], 500);
        }
    }

    /**
     * IMPORT SHP (FIXED: Handling PROJ Error & Null Values)
     */
    public function storeShp(Request $request)
    {
        // Konfigurasi Server Unlimited
        set_time_limit(0); 
        ini_set('memory_limit', '-1'); 
        ini_set('max_execution_time', 0);

        $request->validate([
            'shp_files' => 'required',
            'shp_files.*' => 'file|mimes:zip|max:102400'
        ]);

        $files = $request->file('shp_files');
        if (!is_array($files)) $files = [$files];

        $successCount = 0;
        $failedInfo = [];

        // --- DETEKSI PATH PROJ.DB MILIK GDAL ---
        // Masalah: Postgres punya proj.db versi lama yang konflik dengan GDAL terbaru
        // Solusi: Kita cari path proj.db milik GDAL dan paksa ogr2ogr menggunakannya
        $candidates = [
            'C:\\OSGeo4W\\share\\proj',
            'C:\\OSGeo4W64\\share\\proj',
            'C:\\Program Files\\GDAL\\projlib',
            'C:\\GDAL\\projlib',
            // Tambahkan path instalasi GDAL Anda jika berbeda
        ];

        $projLibPath = null;
        foreach ($candidates as $path) {
            if (file_exists($path . '\\proj.db')) {
                $projLibPath = $path;
                break;
            }
        }
        
        // Buat prefix command untuk set environment variable
        $envPrefix = "";
        if ($projLibPath) {
            $envPrefix = "set \"PROJ_LIB={$projLibPath}\" && ";
        } else {
            // Jika tidak ketemu, coba unset variable global agar GDAL pakai defaultnya
            $envPrefix = "set \"PROJ_LIB=\" && ";
        }

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $uniqueId = uniqid('shp_', true);
            $extractPath = storage_path('app/temp_shp/' . $uniqueId);
            
            try {
                if (!file_exists($extractPath)) mkdir($extractPath, 0777, true);

                // 1. Ekstrak ZIP
                $zipPath = $extractPath . '/source.zip';
                $file->storeAs('temp_shp/' . $uniqueId, 'source.zip');
                
                $zip = new ZipArchive;
                if ($zip->open($zipPath) === TRUE) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                } else {
                    throw new \Exception('Gagal ekstrak file ZIP.');
                }

                // 2. Cari File .shp (Recursive Scan)
                $shpFiles = [];
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath));
                foreach ($iterator as $info) {
                    if ($info->isFile() && strtolower($info->getExtension()) === 'shp') {
                        $shpFiles[] = $info->getPathname();
                    }
                }

                if (empty($shpFiles)) {
                    throw new \Exception('File .shp tidak ditemukan dalam ZIP.');
                }
                $shpFile = $shpFiles[0];

                // 3. Konversi ke GeoJSON (Dengan Fix Environment PROJ_LIB)
                $geojsonFile = $extractPath . '/output.json';
                
                // Command: Set Env -> Execute Ogr2Ogr
                $cmd = "{$envPrefix}ogr2ogr -f GeoJSON -t_srs EPSG:4326 \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                
                $output = [];
                $returnVar = 0;
                exec($cmd, $output, $returnVar);

                // Validasi Output
                if (!file_exists($geojsonFile) || filesize($geojsonFile) < 10) {
                    $errorMsg = implode("\n", $output);
                    if (empty($errorMsg)) $errorMsg = "GDAL tidak merespon. Pastikan GDAL terinstall.";
                    throw new \Exception("Gagal Konversi: " . substr($errorMsg, 0, 200));
                }

                // 4. Baca JSON
                $jsonContent = file_get_contents($geojsonFile);
                if (!$jsonContent) throw new \Exception('Gagal membaca file JSON hasil konversi.');

                $geoData = json_decode($jsonContent, true);

                // Validasi JSON Decoding
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('JSON Error: ' . json_last_error_msg());
                }
                if (!is_array($geoData) || !isset($geoData['features'])) {
                    throw new \Exception('Format GeoJSON tidak valid (tidak ada features).');
                }

                // 5. Insert ke Database (Transactional)
                DB::transaction(function () use ($geoData) {
                    foreach ($geoData['features'] as $feature) {
                        // Skip jika tidak ada geometri
                        if (!isset($feature['geometry']) || empty($feature['geometry'])) continue;

                        $geom = json_encode($feature['geometry']);
                        $props = $feature['properties'] ?? [];
                        
                        // Fallback nama aset
                        $name = null;
                        // Cari key yang mengandung 'nama' atau 'name' atau 'nib'
                        foreach ($props as $key => $val) {
                            if (preg_match('/(nama|name|nib|pemilik)/i', $key) && !empty($val)) {
                                $name = $val; break;
                            }
                        }
                        if (!$name) $name = 'Aset Import';

                        DB::table('spatial_features')->insert([
                            'name' => $name,
                            'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                            // KHUSUS POSTGIS: Wajib Set SRID 4326
                            'geom' => DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('$geom'), 4326)"),
                            'created_at' => now(), 
                            'updated_at' => now()
                        ]);
                    }
                });

                $successCount++;

            } catch (\Exception $e) {
                $failedInfo[] = "$originalName: " . $e->getMessage();
                Log::error("Upload Failed [$originalName]: " . $e->getMessage());
            } finally {
                $this->deleteDirectory($extractPath);
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            if (count($failedInfo) > 0) {
                return response()->json(['status' => 'partial_error', 'message' => implode(' | ', $failedInfo)], 422);
            }
            return response()->json(['status' => 'success', 'message' => "Berhasil import $successCount file!"]);
        }

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

    public function storeDraw(Request $request)
    {
        try {
            DB::table('spatial_features')->insert([
                'name' => $request->name,
                'properties' => json_encode([
                    'type' => $request->type, 
                    'description' => $request->description, 
                    'color' => '#ff0000'
                ]),
                // KHUSUS POSTGIS
                'geom' => DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{$request->geometry}'), 4326)"),
                'created_at' => now(), 
                'updated_at' => now()
            ]);
            return response()->json(['status' => 'success', 'message' => 'Data disimpan!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error'], 500);
        }
    }
}