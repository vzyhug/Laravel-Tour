<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryPlace extends Model
{
    protected $guarded = [];

    public function images()
    {
        return $this->hasMany(ItineraryPlaceImage::class, 'place_id');
    }
}
