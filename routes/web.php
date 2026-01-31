<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::controller(GisController::class)->group(function () {
    // 1. Dashboard Peta
    Route::get('/dashboard', 'index')->name('dashboard');
    Route::get('/api/assets', 'apiData')->name('api.assets');
    
    // 2. MENU BARU: Tabel Data Aset (Fix Error Route not defined)
    Route::get('/aset', 'indexTable')->name('aset.index');
    
    // 3. Simpan Data
    Route::post('/asset/store-draw', 'storeDraw')->name('asset.storeDraw');
    Route::post('/asset/upload-shp', 'storeShp')->name('asset.uploadShp');
});