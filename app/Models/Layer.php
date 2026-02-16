<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'color', 
        'description', 
        'is_active', 
        'mode', // 'standard' atau 'auto_hak' (Layer Utama)
        // Tambahan Warna Dinamis
        'color_hm',
        'color_hgb',
        'color_hp',
        'color_wakaf',
        'color_hgu',
        'color_tn'
    ];

    public function features()
    {
        return $this->hasMany(SpatialFeature::class);
    }
}