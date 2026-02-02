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
     * Helper: Mapping Kode Singkatan ke Kata Kunci Pencarian Lengkap
     */
    private function getHakKeywords($kode) {
        if (!$kode) return [];
        $kode = strtoupper($kode);
        $keywords = [$kode]; 

        if ($kode == 'HM') { $keywords[] = 'Hak Milik'; $keywords[] = 'Milik'; }
        if ($kode == 'HGB') { $keywords[] = 'Hak Guna Bangunan'; $keywords[] = 'Guna Bangunan'; }
        if ($kode == 'HP') { $keywords[] = 'Hak Pakai'; $keywords[] = 'Pakai'; }
        if ($kode == 'WAKAF') { $keywords[] = 'Wakaf'; }
        if ($kode == 'KOSONG' || $kode == 'TANPA HAK') {
            $keywords[] = 'Tanah Negara';
            $keywords[] = 'Belum Ada Hak';
            $keywords[] = 'null';
        }
        return $keywords;
    }

    /**
     * Halaman Utama Peta
     */
    public function index(Request $request)
    {
        return view('admin.map', [
            'lat' => $request->input('lat'),
            'lng' => $request->input('lng'),
            'search' => $request->input('search'),
            'hak' => $request->input('hak')
        ]);
    }

    /**
     * Halaman Tabel Data (Dengan Filter Sumber: Manual vs Import)
     */
    public function indexTable(Request $request)
    {
        $search = $request->input('search');
        $kecamatan = $request->input('kecamatan');
        $desa = $request->input('desa');
        $hak = $request->input('hak');
        $sumber = $request->input('sumber'); // Filter Baru

        $query = SpatialFeature::query();

        // 1. Filter Sumber Data
        if ($sumber == 'manual') {
            // Mencari JSON properties -> type = 'Manual'
            $query->whereRaw("properties->>'type' = 'Manual'");
        } elseif ($sumber == 'import') {
            $query->whereRaw("properties->>'type' = 'Imported'");
        }

        // 2. Filter Pencarian Umum
        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function($q) use ($term) {
                $q->where('name', 'ILIKE', $term)
                  ->orWhereRaw("properties::text ILIKE ?", [$term]);
            });
        }

        // 3. Filter Hak
        if ($hak) {
            $keywords = $this->getHakKeywords($hak);
            $query->where(function($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhereRaw("properties::text ILIKE ?", ['%' . $word . '%']);
                }
            });
        }

        // 4. Filter Wilayah
        if ($kecamatan) $query->whereRaw("properties::text ILIKE ?", ['%' . $kecamatan . '%']);
        if ($desa) $query->whereRaw("properties::text ILIKE ?", ['%' . $desa . '%']);

        $data = $query->select(
            'id', 'name', 'properties', 'created_at',
            DB::raw("ST_AsGeoJSON(ST_Centroid(geom)) as center")
        )
        ->orderBy('id', 'desc')
        ->paginate(15)
        ->withQueryString();

        return view('admin.aset.index', compact('data', 'search', 'kecamatan', 'desa', 'hak', 'sumber'));
    }

    /**
     * API PETA (PostGIS Optimized)
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
            $hak = $request->input('hak');

            $polygonWKT = sprintf("SRID=4326;POLYGON((%s %s, %s %s, %s %s, %s %s, %s %s))", $w, $s, $e, $s, $e, $n, $w, $n, $w, $s);

            $features = [];
            $strategy = '';

            // MODE CLUSTER
            if ($zoom < 14 && empty($search) && empty($hak)) {
                $strategy = 'cluster';
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
            // MODE DETAIL
            else {
                $query = SpatialFeature::query()
                    ->whereRaw("ST_Intersects(geom, ST_GeomFromEWKT(?))", [$polygonWKT]);
                
                if ($search) {
                    $term = '%' . $search . '%';
                    $query->where(function($q) use ($term) {
                        $q->where('name', 'ILIKE', $term)->orWhereRaw("properties::text ILIKE ?", [$term]);
                    });
                }
                if ($hak) {
                    $keywords = $this->getHakKeywords($hak);
                    $query->where(function($q) use ($keywords) {
                        foreach ($keywords as $word) $q->orWhereRaw("properties::text ILIKE ?", ['%' . $word . '%']);
                    });
                }

                $selectGeom = ($zoom > 16 || !empty($search) || !empty($hak)) 
                    ? "ST_AsGeoJSON(geom)" 
                    : "ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.00005))";
                
                $strategy = ($zoom > 16 || !empty($search) || !empty($hak)) ? 'detail' : 'simplified';

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
            return response()->json(['error'=>$e->getMessage()], 500);
        }
    }

    /**
     * IMPORT SHP (Robust Fix GDAL PROJ)
     */
    public function storeShp(Request $request)
    {
        set_time_limit(0); ini_set('memory_limit', '-1'); ini_set('max_execution_time', 0);

        $request->validate(['shp_files' => 'required', 'shp_files.*' => 'file|mimes:zip|max:102400']);
        $files = $request->file('shp_files');
        if (!is_array($files)) $files = [$files];

        $successCount = 0; $failedInfo = [];

        // Fix Path PROJ GDAL
        $candidates = ['C:\\OSGeo4W\\share\\proj', 'C:\\OSGeo4W64\\share\\proj', 'C:\\Program Files\\GDAL\\projlib', 'C:\\GDAL\\projlib'];
        $projLibPath = null;
        foreach ($candidates as $path) { if (file_exists($path . '\\proj.db')) { $projLibPath = $path; break; } }
        $envPrefix = $projLibPath ? "set \"PROJ_LIB={$projLibPath}\" && " : "set \"PROJ_LIB=\" && ";

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $uniqueId = uniqid('shp_', true);
            $extractPath = storage_path('app/temp_shp/' . $uniqueId);
            
            try {
                if (!file_exists($extractPath)) mkdir($extractPath, 0777, true);
                $zip = new ZipArchive;
                if ($zip->open($file->getPathname()) === TRUE) { $zip->extractTo($extractPath); $zip->close(); } 
                else { throw new \Exception('Gagal ekstrak ZIP.'); }

                $shpFiles = [];
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath));
                foreach ($iterator as $info) {
                    if ($info->isFile() && strtolower($info->getExtension()) === 'shp') $shpFiles[] = $info->getPathname();
                }

                if (empty($shpFiles)) throw new \Exception('File .shp tidak ditemukan.');
                $shpFile = $shpFiles[0];
                $geojsonFile = $extractPath . '/output.json';
                
                $cmd = "{$envPrefix}ogr2ogr -f GeoJSON -t_srs EPSG:4326 \"{$geojsonFile}\" \"{$shpFile}\" 2>&1";
                $output = []; $returnVar = 0;
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile) || filesize($geojsonFile) < 10) throw new \Exception("Gagal konversi GDAL.");

                $jsonContent = file_get_contents($geojsonFile);
                $geoData = json_decode($jsonContent, true);
                if (json_last_error() !== JSON_ERROR_NONE || !isset($geoData['features'])) throw new \Exception('JSON Invalid.');

                DB::transaction(function () use ($geoData) {
                    foreach ($geoData['features'] as $feature) {
                        if (!isset($feature['geometry'])) continue;
                        $geom = json_encode($feature['geometry']);
                        $props = $feature['properties'] ?? [];
                        
                        $name = null;
                        foreach ($props as $k => $v) {
                            if (preg_match('/(nama|name|nib|pemilik)/i', $k) && !empty($v)) { $name = $v; break; }
                        }
                        if (!$name) $name = 'Aset Import';

                        DB::table('spatial_features')->insert([
                            'name' => $name,
                            'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                            'geom' => DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('$geom'), 4326)"), 
                            'created_at' => now(), 'updated_at' => now()
                        ]);
                    }
                });
                $successCount++;
            } catch (\Exception $e) {
                $failedInfo[] = "$originalName: " . $e->getMessage();
                Log::error("Upload Fail: " . $e->getMessage());
            } finally {
                $this->deleteDirectory($extractPath);
            }
        }
        if ($request->ajax()) {
            if (count($failedInfo) > 0) return response()->json(['status' => 'partial_error', 'message' => implode(' | ', $failedInfo)], 422);
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

    /**
     * SIMPAN DATA GAMBAR MANUAL (Fitur Baru)
     * - Menghitung Luas Otomatis (PostGIS)
     * - Menyimpan Warna & Detail ke JSON agar kompatibel dengan filter
     */
    public function storeDraw(Request $request)
    {
        try {
            // 1. Validasi Input
            $request->validate([
                'name' => 'required',
                'geometry' => 'required',
                'color' => 'required',
                'status' => 'required'
            ]);

            $geometryJson = $request->geometry;

            // 2. Hitung Luas menggunakan PostGIS (ST_Area Geography = Meter Persegi)
            // ST_GeomFromGeoJSON -> Set SRID 4326 -> Cast to Geography (untuk akurasi meter)
            $sqlLuas = "SELECT ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) as luas_m2";
            $luasResult = DB::selectOne($sqlLuas, [$geometryJson]);
            $luas = $luasResult->luas_m2 ?? 0;

            // 3. Simpan ke Database
            DB::table('spatial_features')->insert([
                'name' => $request->name,
                // Simpan struktur properties agar MIRIP dengan data import
                // Tujuannya agar filter Tipe Hak & Wilayah tetap bekerja untuk data manual
                'properties' => json_encode([
                    'type' => 'Manual', // Penanda Sumber Data
                    'raw_data' => [
                        'TIPEHAK' => $request->status, // Mapping input status ke TIPEHAK
                        'KECAMATAN' => $request->kecamatan ?? '-',
                        'KELURAHAN' => $request->desa ?? '-',
                        'LUASTERTUL' => round($luas, 2), // Simpan hasil hitung luas
                        'PENGGUNAAN' => $request->description
                    ],
                    'color' => $request->color, // Warna custom untuk peta
                    'description' => $request->description
                ]),
                // Simpan Geometry
                'geom' => DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('$geometryJson'), 4326)"),
                'created_at' => now(), 
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => 'Data berhasil disimpan! Luas terhitung: ' . round($luas, 2) . ' m²']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ... method sebelumnya ...

    /**
     * 1. AMBIL DETAIL DATA (Untuk Modal Edit)
     */
    public function show($id)
    {
        $item = DB::table('spatial_features')->where('id', $id)->first();
        if (!$item) return response()->json(['error' => 'Data tidak ditemukan'], 404);

        $props = json_decode($item->properties, true);
        
        // Ratakan data untuk dikirim ke frontend
        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'status' => $props['raw_data']['TIPEHAK'] ?? $props['raw_data']['TIPE_HAK'] ?? '-',
            'kecamatan' => $props['raw_data']['KECAMATAN'] ?? '-',
            'desa' => $props['raw_data']['KELURAHAN'] ?? $props['raw_data']['DESA'] ?? '-',
            'luas' => $props['raw_data']['LUASTERTUL'] ?? $props['raw_data']['LUAS'] ?? 0,
            'description' => $props['description'] ?? $props['raw_data']['PENGGUNAAN'] ?? '',
            'color' => $props['color'] ?? '#ff0000'
        ];

        return response()->json($data);
    }

    /**
     * 2. UPDATE DATA
     */
    public function update(Request $request, $id)
    {
        try {
            $item = DB::table('spatial_features')->where('id', $id)->first();
            if (!$item) return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);

            // Ambil properties lama agar data lain tidak hilang
            $props = json_decode($item->properties, true) ?? [];
            
            // Update data spesifik
            $props['raw_data']['TIPEHAK'] = $request->status;
            $props['raw_data']['KECAMATAN'] = $request->kecamatan;
            $props['raw_data']['KELURAHAN'] = $request->desa;
            $props['raw_data']['PENGGUNAAN'] = $request->description;
            // Jika user edit luas manual
            if($request->has('luas')) {
                $props['raw_data']['LUASTERTUL'] = $request->luas;
            }
            
            // Update tampilan visual
            $props['color'] = $request->color;
            $props['description'] = $request->description;

            DB::table('spatial_features')->where('id', $id)->update([
                'name' => $request->name,
                'properties' => json_encode($props),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => 'Data berhasil diperbarui!']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. HAPUS DATA
     */
    public function destroy($id)
    {
        try {
            DB::table('spatial_features')->delete($id);
            return response()->json(['status' => 'success', 'message' => 'Data berhasil dihapus!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}