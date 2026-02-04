<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Layer extends Model
{
    protected $fillable = ['name', 'color', 'description', 'is_active'];

    public function features()
    {
        return $this->hasMany(SpatialFeature::class);
    }
}