<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layer extends Model
{
    use HasFactory;

    protected $table = 'layers';
    
    protected $fillable = [
        'name',
        'color',
        'description',
        'is_active',
        'mode' // <--- Tambahkan ini
    ];

    // Relasi ke SpatialFeature (Data Aset)
    public function features()
    {
        return $this->hasMany(SpatialFeature::class, 'layer_id');
    }
}