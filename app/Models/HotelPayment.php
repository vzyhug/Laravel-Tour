<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'is_simulated' => 'boolean'];
    }

    public function booking()
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
