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
     * Halaman Utama Peta
     */
    public function index()
    {
        return view('admin.map');
    }

    /**
     * Halaman Tabel Data (Dengan Filter Lengkap)
     */
    public function indexTable(Request $request)
    {
        // 1. Ambil Parameter Filter
        $search = $request->input('search');
        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa');
        $hak = $request->input('hak'); // <--- Filter Baru (Tipe Hak)

        $query = SpatialFeature::query();

        // 2. Terapkan Filter

        // A. Pencarian (Nama / NIB)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('properties->raw_data->NIB', 'like', "%{$search}%");
            });
        }

        // B. Filter Tipe Hak (HM, HGB, HP, Wakaf)
        if ($hak) {
            // Menggunakan 'like' agar lebih fleksibel mencari di dalam JSON
            $query->where('properties->raw_data->TIPE_HAK', 'like', "%{$hak}%");
        }

        // C. Filter Wilayah
        if ($kecamatan) {
            $query->where('properties->raw_data->KECAMATAN', 'like', "%{$kecamatan}%");
        }
        if ($desa) {
            $query->where('properties->raw_data->KELURAHAN', 'like', "%{$desa}%");
        }

        // 3. Eksekusi Query
        $data = $query->select('id', 'name', 'properties', 'created_at')
                      ->orderBy('id', 'desc')
                      ->paginate(15)
                      ->withQueryString(); // Agar parameter filter tetap ada saat pindah halaman

        // 4. Return View dengan data filter
        return view('admin.aset.index', compact('data', 'search', 'kecamatan', 'desa', 'hak'));
    }

    /**
     * API PETA CERDAS (Clustering + Filtering + Coloring)
     * Menangani logika Level of Detail (LOD) untuk data besar.
     */
    public function apiData(Request $request)
    {
        // Cek parameter dasar
        if (!$request->has(['north', 'south', 'east', 'west', 'zoom'])) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        try {
            $n = $request->north;
            $s = $request->south;
            $e = $request->east;
            $w = $request->west;
            $zoom = (int) $request->zoom;
            
            // Ambil Filter dari Peta
            $search = $request->input('search');
            $filterHak = $request->input('hak');

            // Polygon Area Layar (Viewport)
            $polygonWKT = sprintf(
                "POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))",
                $w, $s, $e, $s, $e, $n, $w, $n, $w, $s
            );

            $features = [];
            $strategy = '';

            // === LOGIKA LOD (LEVEL OF DETAIL) ===

            // 1. MODE CLUSTER: Jika Zoom Jauh (<14) DAN Tidak ada filter aktif
            if ($zoom < 14 && empty($search) && empty($filterHak)) {
                $strategy = 'cluster';
                // Ukuran grid menyesuaikan zoom level
                $gridSize = $zoom < 10 ? 0.05 : 0.005;

                // Query Clustering: Hitung jumlah data per grid area
                $clusters = DB::table('spatial_features')
                    ->select(DB::raw("COUNT(*) as total, ST_AsGeoJSON(ST_Centroid(geom)) as center"))
                    ->whereRaw("ST_Intersects(geom, ST_GeomFromText(?))", [$polygonWKT])
                    ->groupByRaw("FLOOR(ST_X(ST_Centroid(geom)) / $gridSize), FLOOR(ST_Y(ST_Centroid(geom)) / $gridSize)")
                    ->get();

                foreach ($clusters as $cluster) {
                    if (!$cluster->center) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => json_decode($cluster->center),
                        'properties' => [
                            'type' => 'cluster',
                            'count' => $cluster->total,
                        ]
                    ];
                }
            }
            // 2. MODE GEOMETRI: Jika Zoom Dekat ATAU Ada Filter Aktif
            else {
                $query = SpatialFeature::query();

                // Filter Area
                $query->whereRaw("ST_Intersects(geom, ST_GeomFromText(?))", [$polygonWKT]);

                // Filter Pencarian
                if ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('properties->raw_data->NIB', 'like', "%{$search}%")
                          ->orWhere('properties->raw_data->PEMILIK', 'like', "%{$search}%");
                    });
                }

                // Filter Hak
                if ($filterHak) {
                    $query->where('properties->raw_data->TIPE_HAK', 'like', "%{$filterHak}%");
                }

                // Tentukan Strategi Simplifikasi
                // Jika zoom sangat dekat (>16) atau ada pencarian, tampilkan detail penuh
                if ($zoom > 16 || !empty($search)) {
                    $strategy = 'detail';
                    $selectGeom = "ST_AsGeoJSON(geom)";
                } else {
                    $strategy = 'simplified';
                    // ST_Simplify: Mengurangi detail verteks untuk memperingan load (0.00005 ~= 5 meter)
                    $selectGeom = "ST_AsGeoJSON(ST_Simplify(geom, 0.00005))";
                }

                $data = $query->select(
                    'id', 'name', 'properties',
                    DB::raw("$selectGeom as geometry")
                )
                ->limit(2500) // Batas aman browser
                ->get();

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
            }

            return response()->json([
                'type' => 'FeatureCollection',
                'features' => $features,
                'strategy' => $strategy,
                'count' => count($features)
            ]);

        } catch (\Exception $e) {
            Log::error('API Peta Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * IMPORT SHP (Smart Batch Upload)
     * Mengelola upload file .zip SHP dalam jumlah banyak tanpa timeout.
     */
    public function storeShp(Request $request)
    {
        // Konfigurasi Server Unlimited untuk proses berat
        set_time_limit(0); 
        ini_set('memory_limit', '-1'); 
        ini_set('max_execution_time', 0);

        $request->validate([
            'shp_files' => 'required',
            'shp_files.*' => 'file|mimes:zip|max:102400' // Max 100MB per file
        ]);

        $files = $request->file('shp_files');
        if (!is_array($files)) $files = [$files];

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

                // 3. Cari file .shp (Recursive)
                $shpFiles = glob($extractPath . '/**/*.shp');
                if (empty($shpFiles)) $shpFiles = glob($extractPath . '/*.shp');
                if (empty($shpFiles)) throw new \Exception('File .shp tidak ditemukan dalam ZIP.');
                $shpFile = $shpFiles[0];

                // 4. Konversi ke GeoJSON menggunakan ogr2ogr
                $geojsonFile = $extractPath . '/output.json';
                // 2>&1 digunakan untuk menangkap error output dari command line
                $cmd = "ogr2ogr -f GeoJSON -t_srs EPSG:4326 \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                $output = [];
                $returnVar = 0;
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile) || filesize($geojsonFile) == 0) {
                    $err = implode("\n", $output);
                    throw new \Exception("Gagal Konversi OGR2OGR: " . substr($err, 0, 200));
                }

                // 5. Insert ke Database
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
                            // Menggunakan ST_GeomFromGeoJSON tanpa SRID tambahan (SRID 0 default)
                            // Ini penting untuk kompatibilitas MySQL 8.0 dan menghindari error "Latitude out of range"
                            'geom' => DB::raw("ST_GeomFromGeoJSON('$geom')"), 
                            'created_at' => now(), 
                            'updated_at' => now()
                        ]);
                    }
                });

                $successCount++;
                $this->deleteDirectory($extractPath);

            } catch (\Exception $e) {
                $failedInfo[] = "$originalName: " . $e->getMessage();
                Log::error("Import Gagal $originalName: " . $e->getMessage());
                // Bersihkan temp folder jika gagal
                if ($extractPath) $this->deleteDirectory($extractPath);
            }
        }

        // Response JSON untuk JavaScript (AJAX)
        if ($request->ajax() || $request->wantsJson()) {
            if (count($failedInfo) > 0) {
                return response()->json(['status' => 'partial_error', 'message' => implode(', ', $failedInfo)], 422);
            }
            return response()->json(['status' => 'success', 'message' => 'Berhasil']);
        }

        // Fallback untuk upload biasa
        return back()->with('success', "Berhasil import $successCount file!");
    }

    // Helper: Hapus folder rekursif
    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    /**
     * Simpan Data Manual (Digambar di Peta)
     */
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
                'geom' => DB::raw("ST_GeomFromGeoJSON('{$request->geometry}')"),
                'created_at' => now(), 
                'updated_at' => now()
            ]);
            return response()->json(['status' => 'success', 'message' => 'Data disimpan!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error'], 500);
        }
    }
}