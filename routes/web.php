<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::controller(GisController::class)->group(function () {
    Route::get('/dashboard', 'index')->name('dashboard');
    Route::get('/api/assets', 'apiData')->name('api.assets');
    Route::post('/asset/store-draw', 'storeDraw')->name('asset.storeDraw');
    Route::post('/asset/upload-shp', 'storeShp')->name('asset.uploadShp');
});