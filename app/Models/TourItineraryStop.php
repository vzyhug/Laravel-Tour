<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourItineraryStop extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function place()
    {
        return $this->belongsTo(ItineraryPlace::class, 'place_id');
    }

    public function schedule()
    {
        return $this->belongsTo(TourSchedule::class, 'schedule_id');
    }
}
