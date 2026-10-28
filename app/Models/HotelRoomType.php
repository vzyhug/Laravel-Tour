<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean', 'max_guests' => 'integer'];
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function inventory()
    {
        return $this->hasMany(HotelRoomInventory::class, 'room_type_id');
    }

    public function bookings()
    {
        return $this->hasMany(HotelBooking::class, 'room_type_id');
    }
}
