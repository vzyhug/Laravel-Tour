<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryConsultation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'travel_date' => 'date', 'valid_until' => 'date', 'quoted_total' => 'integer'];
    }

    public function itinerary()
    {
        return $this->belongsTo(CustomerItinerary::class, 'itinerary_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
