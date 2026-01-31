<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Untuk logging error
use ZipArchive; // Wajib ada untuk ekstrak zip

class GisController extends Controller
{
    /**
     * 1. Halaman Dashboard Peta
     */
    public function index()
    {
        return view('admin.map');
    }

    /**
     * 2. API Data GeoJSON (OPTIMIZED)
     * Endpoint ini memuat data dari database untuk ditampilkan di Leaflet.
     */
    public function apiData()
    {
        // === KONFIGURASI SERVER KHUSUS REQUEST INI ===
        // Set Unlimited Time & Memory agar tidak crash saat load data besar
        set_time_limit(0); 
        ini_set('memory_limit', '-1'); 

        try {
            // Mengambil data spasial
            // Jika peta sangat berat/lag, ganti baris 'geom' dengan:
            // DB::raw('ST_AsGeoJSON(ST_Simplify(geom, 0.0001)) as geometry')
            $data = SpatialFeature::select(
                'id', 
                'name', 
                'properties',
                DB::raw('ST_AsGeoJSON(geom) as geometry') 
            )->get();

            $features = [];
            foreach ($data as $item) {
                // Skip jika geometry kosong
                if (!$item->geometry) continue;

                $features[] = [
                    'type' => 'Feature',
                    // Decode string JSON geometry dari MySQL menjadi Objek PHP
                    'geometry' => json_decode($item->geometry),
                    
                    // Decode properties:
                    // Karena di Model SpatialFeature sudah ada $casts = ['properties' => 'array'],
                    // maka $item->properties sudah berupa Array. Jangan di-json_decode lagi!
                    'properties' => array_merge(
                        ['id' => $item->id, 'name' => $item->name],
                        $item->properties ?? [] 
                    )
                ];
            }

            return response()->json([
                'type' => 'FeatureCollection',
                'features' => $features
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal memuat API Peta: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. Simpan Data Manual (Fitur Draw di Peta)
     */
    public function storeDraw(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'geometry' => 'required' 
        ]);

        try {
            $properties = [
                'type' => $request->type,
                'description' => $request->description,
                'color' => $this->getColorByType($request->type)
            ];

            DB::table('spatial_features')->insert([
                'name' => $request->name,
                'properties' => json_encode($properties),
                'geom' => DB::raw("ST_GeomFromGeoJSON('{$request->geometry}')"),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => 'Data berhasil disimpan!']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 4. Import File SHP (OPTIMIZED FOR LARGE FILES)
     */
    public function storeShp(Request $request)
    {
        // === KONFIGURASI SERVER KHUSUS UPLOAD ===
        set_time_limit(0); // Unlimited execution time
        ini_set('memory_limit', '-1'); // Unlimited memory usage
        ini_set('max_execution_time', 0); 

        $request->validate(['shp_file' => 'required|file|mimes:zip']);

        try {
            // Gunakan Transaksi DB: Jika error di tengah jalan, semua data batal masuk (Rollback)
            return DB::transaction(function () use ($request) {
                
                // A. Upload & Extract ZIP
                $file = $request->file('shp_file');
                // Nama file unik
                $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $path = $file->storeAs('temp_shp', $filename);
                
                $fullPath = storage_path('app/' . $path);
                $extractPath = storage_path('app/temp_shp/' . time());

                $zip = new ZipArchive;
                if ($zip->open($fullPath) === TRUE) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                } else {
                    throw new \Exception('Gagal mengekstrak file ZIP.');
                }

                // B. Cari file .shp
                $shpFile = glob($extractPath . '/*.shp')[0] ?? null;
                if (!$shpFile) {
                    throw new \Exception('File .shp tidak ditemukan dalam ZIP.');
                }

                // C. Konversi SHP ke GeoJSON dengan ogr2ogr
                $geojsonFile = $extractPath . '/output.json';
                // Pastikan path ogr2ogr benar. Jika di Windows dan error, coba gunakan path absolut ke .exe
                $cmd = "ogr2ogr -f GeoJSON -t_srs EPSG:4326 \"{$geojsonFile}\" \"{$shpFile}\"";
                
                // Eksekusi perintah shell
                $output = null;
                $returnVar = null;
                exec($cmd, $output, $returnVar);

                if (!file_exists($geojsonFile)) {
                     // Coba debug output jika gagal
                     Log::error('OGR2OGR Error: ' . implode("\n", $output));
                     throw new \Exception('Gagal konversi SHP. Pastikan GDAL/ogr2ogr terinstall dan terbaca sistem.');
                }

                // D. Baca File GeoJSON Hasil Konversi
                $jsonContent = file_get_contents($geojsonFile);
                if (!$jsonContent) {
                    throw new \Exception('File GeoJSON hasil konversi kosong.');
                }

                $geoData = json_decode($jsonContent, true);
                if (!isset($geoData['features'])) {
                    throw new \Exception('Format GeoJSON tidak valid.');
                }

                // E. Insert ke Database (Looping)
                foreach ($geoData['features'] as $feature) {
                    $geom = json_encode($feature['geometry']);
                    $props = $feature['properties'] ?? [];
                    
                    // Deteksi Nama Aset dari berbagai kemungkinan key (case insensitive bisa ditambah jika perlu)
                    $name = $props['nama'] ?? $props['NAME'] ?? $props['Name'] ?? 
                            $props['NIB'] ?? $props['nib'] ?? 'Aset Import'; 
                    
                    DB::table('spatial_features')->insert([
                        'name' => $name,
                        'properties' => json_encode(['type' => 'Imported', 'raw_data' => $props]),
                        'geom' => DB::raw("ST_GeomFromGeoJSON('$geom')"),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // Opsional: Hapus folder temp (Uncomment jika ingin membersihkan server)
                // \Illuminate\Support\Facades\File::deleteDirectory($extractPath);
                
                return back()->with('success', 'File SHP berhasil diimport! Total data: ' . count($geoData['features']));
            });

        } catch (\Exception $e) {
            // Log error lengkap untuk debugging developer
            Log::error('Import SHP Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return back()->with('error', 'Gagal Import: ' . $e->getMessage());
        }
    }

    /**
     * Helper: Menentukan warna berdasarkan tipe aset
     */
    private function getColorByType($type) {
        return match($type) {
            'Tanah' => '#ff0000', // Merah
            'Bangunan' => '#0000ff', // Biru
            'Jalan' => '#00ff00', // Hijau
            default => '#ffa500', // Orange
        };
    }
}