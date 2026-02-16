<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpatialFeature;
use App\Models\Layer; 
use Illuminate\Support\Facades\DB;
use App\Jobs\AnalyzeOverlapsJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatisticController extends Controller
{
    public function index()
    {
        // 1. Ambil semua layer untuk dropdown filter di view
        $layers = Layer::orderBy('name', 'asc')->get();
        
        // 2. Data statistik awal (opsional, jika ingin load default)
        // Disini kita hanya kirim variabel $layers untuk view
        return view('admin.statistic.index', compact('layers'));
    }

    // Fungsi AJAX untuk menampilkan data di tabel (Preview)
    public function runAnalysis(Request $request)
    {
        // Gunakan helper untuk filter data
        $data = $this->getFilteredData($request);
        
        // Render view partial menjadi string HTML
        // Pastikan Anda sudah membuat view: resources/views/admin/statistic/table_partial.blade.php
        $html = view('admin.statistic.table_partial', compact('data'))->render();
        
        return response()->json([
            'status' => 'success', 
            'html' => $html,
            'count' => $data->count()
        ]);
    }

    // Fungsi Baru: Export ke Excel (CSV)
    public function export(Request $request)
    {
        // Ambil data yang sama persis dengan yang dilihat user
        $data = $this->getFilteredData($request);
        
        $fileName = 'Data_Aset_GIS_' . date('Y-m-d_H-i') . '.csv';

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Callback untuk streaming file (hemat memori)
        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Header CSV
            fputcsv($file, ['No', 'Nama Aset', 'Layer', 'Status Hak', 'Kecamatan', 'Desa/Kelurahan', 'Luas (m2)', 'Penggunaan', 'Tanggal Input']);

            // Isi Data Baris per Baris
            foreach ($data as $index => $item) {
                $props = json_decode($item->properties, true);
                $raw = $props['raw_data'] ?? [];

                // Ambil data detail dengan fallback
                $hak = $raw['TIPEHAK'] ?? $raw['TIPE_HAK'] ?? $raw['HAK'] ?? '-';
                $kec = $raw['KECAMATAN'] ?? '-';
                $desa = $raw['KELURAHAN'] ?? $raw['DESA'] ?? '-';
                $luas = $raw['LUASTERTUL'] ?? $raw['LUAS'] ?? 0;
                $guna = $props['description'] ?? $raw['PENGGUNAAN'] ?? '-';

                fputcsv($file, [
                    $index + 1,
                    $item->name,
                    $item->layer->name ?? 'Tanpa Layer', // Ambil nama layer dari relasi
                    $hak,
                    $kec,
                    $desa,
                    $luas,
                    $guna,
                    $item->created_at->format('Y-m-d H:i')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Helper: Pusat Logika Filter (Agar konsisten antara View & Export)
    private function getFilteredData(Request $request)
    {
        $query = SpatialFeature::query()->with('layer');

        // 1. Filter Pencarian Nama
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where('name', 'ILIKE', $term);
        }

        // 2. Filter Layer (Fitur Baru)
        if ($request->filled('layer_id')) {
            $query->where('layer_id', $request->layer_id);
        }

        // 3. Filter Kecamatan
        if ($request->filled('kecamatan')) {
            $kec = '%' . $request->kecamatan . '%';
            $query->whereRaw("properties->'raw_data'->>'KECAMATAN' ILIKE ?", [$kec]);
        }

        // 4. Filter Tipe Hak
        if ($request->filled('hak')) {
            $hak = strtoupper($request->hak);
            $query->where(function($q) use ($hak) {
                $q->whereRaw("properties->'raw_data'->>'TIPEHAK' ILIKE ?", ['%'.$hak.'%'])
                  ->orWhereRaw("properties->'raw_data'->>'TIPE_HAK' ILIKE ?", ['%'.$hak.'%'])
                  ->orWhereRaw("properties->'raw_data'->>'HAK' ILIKE ?", ['%'.$hak.'%']);
            });
        }

        // Ambil data terbaru
        return $query->orderBy('created_at', 'desc')->get();
    }
}