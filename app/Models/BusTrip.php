<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusTrip extends Model
{
    use HasFactory;

    protected $table = 'bus_trips';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function resource()
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }

    public function stops()
    {
        return $this->hasMany(BusTripStop::class, 'trip_id')->orderBy('position');
    }
}
