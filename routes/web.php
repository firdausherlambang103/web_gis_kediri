<?php

use Illuminate\Support\Facades\Route;

// 1. Import Controller Autentikasi Manual
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

// 2. Import Controller Utama
use App\Http\Controllers\GisController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\LayerController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root ke dashboard (akan dicegat middleware auth jika belum login)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// =============================================================================
// AUTHENTICATION ROUTES (MANUAL)
// =============================================================================
// Kita menggunakan controller manual agar bisa cek status 'is_active' dan catat log.

// Login & Logout
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Register
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.post');


// =============================================================================
// GROUP: PUBLIC / USER LOGIN (Bisa diakses semua user yang sudah login & aktif)
// =============================================================================
Route::middleware(['auth'])->group(function () {

    // --- DASHBOARD & PETA (GIS) ---
    Route::controller(GisController::class)->group(function () {
        // Halaman Peta Utama
        Route::get('/dashboard', 'index')->name('dashboard');
        
        // API GeoJSON untuk Peta (Ajax)
        Route::get('/api/assets', 'apiData')->name('api.assets');
        
        // Halaman Tabel Data Aset
        Route::get('/aset', 'indexTable')->name('aset.index');
        
        // Detail Aset (Modal/View)
        Route::get('/asset/{id}', 'show')->name('asset.show'); 
        
        // Dropdown List Layer
        Route::get('/layers', 'getLayers')->name('layer.get'); 
    });

    // --- STATISTIK & ANALISIS ---
    Route::controller(StatisticController::class)->group(function () {
        // Halaman Statistik Utama
        Route::get('/statistics', 'index')->name('statistics.index');
        
        // Export Data Aset ke Excel
        Route::get('/statistics/export', 'export')->name('statistics.export');

        // Export Data Overlap (Tumpang Tindih) ke Excel - BARU
        Route::get('/statistics/export-overlap', 'exportOverlap')->name('statistics.exportOverlap');
        
        // Jalankan Analisis Python (Bisa GET untuk cek status atau POST untuk trigger)
        Route::any('/statistics/run', 'runAnalysis')->name('statistics.run'); 
    });

});

// =============================================================================
// GROUP: ADMIN ONLY (Hanya User dengan Role 'admin')
// =============================================================================
Route::middleware(['auth', 'admin'])->group(function () {

    // --- MANAJEMEN ASET (CRUD Full & Upload) ---
    Route::controller(GisController::class)->group(function () {
        // Simpan Gambar Manual (Polygon/Line/Point)
        Route::post('/asset/draw', 'storeDraw')->name('asset.storeDraw');   
        
        // Upload File SHP (Shapefile)
        Route::post('/asset/upload', 'storeShp')->name('asset.uploadShp');  
        
        // Update Data Aset
        Route::put('/asset/{id}', 'update')->name('asset.update');          
        
        // Hapus Aset
        Route::delete('/asset/{id}', 'destroy')->name('asset.destroy');     
        
        // Tambah Layer Baru (Quick Add via Peta)
        Route::post('/layer', 'storeLayer')->name('layer.store');           
        
        // Update Warna Layer (Ajax Color Picker)
        Route::post('/layer/update-color', 'updateLayerColor')->name('layer.updateColor');
    });

    // --- MANAJEMEN MASTER LAYER (Halaman Khusus) ---
    // Menyediakan route index, store, update, destroy otomatis
    Route::resource('master-layer', LayerController::class)->except(['create', 'show', 'edit']);

    // --- MANAJEMEN SISTEM (User & Log) ---
    Route::controller(AdminController::class)->group(function() {
        // Daftar User
        Route::get('/admin/users', 'users')->name('admin.users');
        
        // Approve User Baru
        Route::post('/admin/users/{id}/approve', 'approveUser')->name('admin.users.approve');
        
        // Hapus User
        Route::delete('/admin/users/{id}', 'deleteUser')->name('admin.users.delete');
        
        // Log Aktivitas Sistem
        Route::get('/admin/logs', 'logs')->name('admin.logs');
    });

});