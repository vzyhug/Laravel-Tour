<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryPlaceImage extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function place()
    {
        return $this->belongsTo(ItineraryPlace::class, 'place_id');
    }
}
