<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelBookingNight extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['stay_date' => 'date', 'nightly_price' => 'integer', 'rooms' => 'integer'];
    }

    public function booking()
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function inventory()
    {
        return $this->belongsTo(HotelRoomInventory::class, 'inventory_id');
    }
}
