<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\StatisticController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect halaman awal ke Dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Group Route untuk GIS Controller
Route::controller(GisController::class)->group(function () {
    // 1. Halaman Peta (Dashboard Utama)
    Route::get('/dashboard', 'index')->name('dashboard');
    
    // 2. API Data Peta (Dipanggil oleh AJAX/Leaflet)
    Route::get('/api/assets', 'apiData')->name('api.assets');
    
    // 3. Halaman Tabel Data Aset
    Route::get('/aset', 'indexTable')->name('aset.index');
    
    // 4. Proses Simpan & Upload
    Route::post('/asset/store-draw', 'storeDraw')->name('asset.storeDraw');
    Route::post('/asset/upload-shp', 'storeShp')->name('asset.uploadShp');
});

// Group Route untuk Statistik (Menu Baru)
Route::controller(StatisticController::class)->group(function () {
    Route::get('/statistics', 'index')->name('statistics.index');
});