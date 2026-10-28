<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelBooking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['check_in' => 'date', 'check_out' => 'date', 'expires_at' => 'datetime', 'cancelled_at' => 'datetime', 'total_amount' => 'integer', 'rooms' => 'integer', 'guests' => 'integer'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function roomType()
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function nights()
    {
        return $this->hasMany(HotelBookingNight::class);
    }

    public function payment()
    {
        return $this->hasOne(HotelPayment::class);
    }
}
