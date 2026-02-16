<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\LogHelper;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // Default user biasa
            'is_active' => false, // Default NON-AKTIF (Perlu ACC Admin)
        ]);
        
        // Jangan langsung login, arahkan ke login dengan pesan
        return redirect()->route('login')->with('success', 'Registrasi berhasil! Mohon tunggu Admin mengaktifkan akun Anda.');
    }
}