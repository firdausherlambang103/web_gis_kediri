<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\StatisticController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root ke dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Group Route untuk GIS Controller
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

    // 4. Manajemen Layer (INI YANG MENYELESAIKAN ERROR ANDA)
    Route::post('/layer', 'storeLayer')->name('layer.store');           // Simpan Layer Baru
    Route::get('/layers', 'getLayers')->name('layer.get');              // Ambil Daftar Layer (JSON)
});

// Group Route untuk Statistik & Analisis
Route::controller(StatisticController::class)->group(function () {
    Route::get('/statistics', 'index')->name('statistics.index');
    Route::post('/statistics/run', 'runAnalysis')->name('statistics.run');
});