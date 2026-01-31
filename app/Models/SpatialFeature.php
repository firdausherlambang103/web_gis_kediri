<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpatialFeature extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'properties', 'geom'];
    protected $casts = ['properties' => 'array'];

    // Scope untuk mengubah format Geometry MySQL ke GeoJSON
    public function scopeAsGeoJSON($query)
    {
        return $query->select(
            'id', 'name', 'properties',
            DB::raw('ST_AsGeoJSON(geom) as geometry') 
        );
    }
}