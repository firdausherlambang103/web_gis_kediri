<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\LayerController; // <-- Tambahkan Controller Baru

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root ke dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Group Route untuk GIS Controller (Peta & Aset)
Route::controller(GisController::class)->group(function () {
    
    // 1. Halaman Utama & API Peta
    Route::get('/dashboard', 'index')->name('dashboard');
    Route::get('/api/assets', 'apiData')->name('api.assets');

    // 2. Halaman Tabel Data Aset
    Route::get('/aset', 'indexTable')->name('aset.index');

    // 3. Manajemen Aset (CRUD & Upload)
    Route::post('/asset/draw', 'storeDraw')->name('asset.storeDraw');   // Simpan Gambar Manual
    Route::post('/asset/upload', 'storeShp')->name('asset.uploadShp');  // Upload SHP
    Route::get('/asset/{id}', 'show')->name('asset.show');              // Ambil Detail (untuk Edit)
    Route::put('/asset/{id}', 'update')->name('asset.update');          // Simpan Edit
    Route::delete('/asset/{id}', 'destroy')->name('asset.destroy');     // Hapus Aset

    // 4. Manajemen Layer (AJAX di Peta)
    // Route ini tetap ada untuk mendukung fitur "Tambah Layer" langsung dari peta
    Route::post('/layer', 'storeLayer')->name('layer.store');           
    Route::get('/layers', 'getLayers')->name('layer.get');              
});

// Group Route untuk Master Data Layer (Halaman Khusus Management)
// Ini route baru untuk halaman CRUD Master Layer
Route::resource('master-layer', LayerController::class)->except(['create', 'show', 'edit']);

// Group Route untuk Statistik & Analisis
Route::controller(StatisticController::class)->group(function () {
    Route::get('/statistics', 'index')->name('statistics.index');
    Route::post('/statistics/run', 'runAnalysis')->name('statistics.run');
});