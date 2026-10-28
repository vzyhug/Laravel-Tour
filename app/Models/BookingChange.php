<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingChange extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'previous_amount' => 'decimal:2', 'new_amount' => 'decimal:2'];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function fromDeparture()
    {
        return $this->belongsTo(TourDeparture::class, 'from_departure_id');
    }

    public function toDeparture()
    {
        return $this->belongsTo(TourDeparture::class, 'to_departure_id');
    }
}
