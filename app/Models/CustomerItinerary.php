<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerItinerary extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(CustomerItineraryItem::class, 'itinerary_id')->orderBy('day_number')->orderBy('position');
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class, 'tour_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function consultations()
    {
        return $this->hasMany(ItineraryConsultation::class, 'itinerary_id')->latest();
    }
}
