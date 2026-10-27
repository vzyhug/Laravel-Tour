<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerItineraryItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function place()
    {
        return $this->belongsTo(ItineraryPlace::class, 'place_id');
    }

    public function itinerary()
    {
        return $this->belongsTo(CustomerItinerary::class, 'itinerary_id');
    }
}
