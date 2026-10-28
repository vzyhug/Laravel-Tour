<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryPlaceImage extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getUrlAttribute(): string
    {
        return asset($this->path);
    }

    public function place()
    {
        return $this->belongsTo(ItineraryPlace::class, 'place_id');
    }
}
