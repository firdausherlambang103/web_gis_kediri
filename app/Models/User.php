<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail; // Uncomment jika ingin fitur verifikasi email
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',      // Kolom Role: 'admin' atau 'user'
        'is_active', // Kolom Status: true (sudah di-acc) atau false (pending)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean', // Penting: Casting ke boolean agar mudah dicek di Blade/Controller
    ];

    // =========================================================================
    // HELPER METHODS (Untuk Cek Hak Akses)
    // =========================================================================

    /**
     * Cek apakah user memiliki role 'admin'
     * Penggunaan: if(auth()->user()->isAdmin()) { ... }
     * * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Cek apakah akun aktif (sudah di-ACC admin)
     * * @return bool
     */
    public function isActive()
    {
        return $this->is_active;
    }

    // =========================================================================
    // RELATIONSHIPS (Relasi Database)
    // =========================================================================

    /**
     * Relasi ke ActivityLog (One to Many)
     * Satu user bisa memiliki banyak catatan log aktivitas
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}