<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomInventory extends Model
{
    protected $table = 'hotel_room_inventory';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['stay_date' => 'date', 'nightly_price' => 'integer', 'total_rooms' => 'integer', 'available_rooms' => 'integer'];
    }

    public function roomType()
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }
}
