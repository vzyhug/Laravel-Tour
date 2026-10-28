<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItineraryPlace extends Model
{
    use HasFactory;

    public const CATEGORIES = ['museum' => 'Bảo tàng', 'coastal' => 'Biển & đảo', 'landmark' => 'Công trình & di tích', 'spiritual' => 'Văn hóa tâm linh', 'nature' => 'Thiên nhiên', 'culture' => 'Làng bản & văn hóa', 'market' => 'Chợ & ẩm thực', 'urban' => 'Khu vực khám phá'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['verified_on' => 'date'];
    }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'destination_itinerary_place');
    }

    public function images()
    {
        return $this->hasMany(ItineraryPlaceImage::class, 'place_id');
    }
}
