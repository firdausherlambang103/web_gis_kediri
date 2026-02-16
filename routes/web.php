<?php

use Illuminate\Support\Facades\Route;
// Hapus Auth::routes(), kita pakai controller manual ini:
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

use App\Http\Controllers\GisController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\LayerController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. Redirect root ke dashboard (akan dicegat middleware auth jika belum login)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// =============================================================================
// AUTHENTICATION ROUTES (MANUAL)
// =============================================================================
// Kita pakai ini MENGGANTIKAN Auth::routes() agar bisa custom logic (is_active & log)

// Login & Logout
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Register
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.post');


// =============================================================================
// GROUP: PUBLIC / USER LOGIN (Bisa diakses user yang sudah login & aktif)
// =============================================================================
Route::middleware(['auth'])->group(function () {

    // --- DASHBOARD & PETA (GIS) ---
    Route::controller(GisController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::get('/api/assets', 'apiData')->name('api.assets');
        Route::get('/aset', 'indexTable')->name('aset.index');
        Route::get('/asset/{id}', 'show')->name('asset.show'); 
        Route::get('/layers', 'getLayers')->name('layer.get'); 
    });

    // --- STATISTIK ---
    Route::controller(StatisticController::class)->group(function () {
        Route::get('/statistics', 'index')->name('statistics.index');
        Route::any('/statistics/run', 'runAnalysis')->name('statistics.run'); 
        Route::get('/statistics/export', 'export')->name('statistics.export');
    });

});

// =============================================================================
// GROUP: ADMIN ONLY (Hanya User dengan Role 'admin')
// =============================================================================
Route::middleware(['auth', 'admin'])->group(function () {

    // --- MANAJEMEN ASET (CRUD Full) ---
    Route::controller(GisController::class)->group(function () {
        Route::post('/asset/draw', 'storeDraw')->name('asset.storeDraw');   
        Route::post('/asset/upload', 'storeShp')->name('asset.uploadShp');  
        Route::put('/asset/{id}', 'update')->name('asset.update');          
        Route::delete('/asset/{id}', 'destroy')->name('asset.destroy');     
        Route::post('/layer', 'storeLayer')->name('layer.store');           
        Route::post('/layer/update-color', 'updateLayerColor')->name('layer.updateColor');
    });

    // --- MANAJEMEN MASTER LAYER ---
    Route::resource('master-layer', LayerController::class)->except(['create', 'show', 'edit']);

    // --- MANAJEMEN USER & LOG ---
    Route::controller(AdminController::class)->group(function() {
        Route::get('/admin/users', 'users')->name('admin.users');
        Route::post('/admin/users/{id}/approve', 'approveUser')->name('admin.users.approve');
        Route::delete('/admin/users/{id}', 'deleteUser')->name('admin.users.delete');
        Route::get('/admin/logs', 'logs')->name('admin.logs');
    });

});