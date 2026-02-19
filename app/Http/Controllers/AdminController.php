<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog; // Pastikan Model ini di-import
use App\Helpers\LogHelper;  // Pastikan Helper ini di-import

class AdminController extends Controller
{
    // =========================================================================
    // MANAJEMEN USER
    // =========================================================================

    public function users()
    {
        // Ambil data user, urutkan dari yang terbaru
        $users = User::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    public function approveUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);
        
        // Catat Log
        LogHelper::record('APPROVE_USER', $user->name, 'Admin mengaktifkan user ini');
        
        return back()->with('success', 'User berhasil diaktifkan');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $namaUser = $user->name; // Simpan nama sebelum dihapus untuk log
        $user->delete();
        
        // Catat Log
        LogHelper::record('DELETE_USER', $namaUser, 'Admin menghapus user');
        
        return back()->with('success', 'User berhasil dihapus');
    }

    // =========================================================================
    // LOG AKTIVITAS (Method yang hilang sebelumnya)
    // =========================================================================

    public function logs()
    {
        // Ambil data log beserta relasi usernya agar tidak N+1 Query
        $logs = ActivityLog::with('user')->orderBy('created_at', 'desc')->paginate(20);
        
        return view('admin.logs.index', compact('logs'));
    }
}