<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformTripStop extends Model
{
    use HasFactory;

    protected $table = 'platform_trip_stops';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['time_at' => 'datetime'];
    }

    public $timestamps = false;

    public function trip()
    {
        return $this->belongsTo(PlatformTrip::class, 'trip_id');
    }
}
