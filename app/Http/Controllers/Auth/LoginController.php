<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\LogHelper; // Import Helper Log

class LoginController extends Controller
{
    // Tampilkan Form Login
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // Proses Login
    public function login(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Coba Login (Auth Attempt)
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // 3. CEK STATUS AKTIF (Fitur Approval Admin)
            if (!$user->is_active && $user->role !== 'admin') {
                // Jika user biasa & belum aktif, tendang keluar
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Akun Anda belum diaktifkan oleh Admin. Mohon tunggu persetujuan.',
                ]);
            }

            // 4. Regenerasi Session (Keamanan)
            $request->session()->regenerate();

            // 5. CATAT LOG LOGIN
            LogHelper::record('LOGIN', 'System', "User {$user->name} berhasil login.");

            // 6. Redirect ke Dashboard
            return redirect()->intended('dashboard');
        }

        // Jika Gagal Login
        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    // Proses Logout
    public function logout(Request $request)
    {
        // Catat Log Logout (Opsional, sebelum session hancur)
        if(Auth::check()){
            LogHelper::record('LOGOUT', 'System', "User " . Auth::user()->name . " logout.");
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}