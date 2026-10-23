<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformTrip extends Model
{
    use HasFactory;

    protected $table = 'platform_trips';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function resource()
    {
        return $this->belongsTo(PlatformResource::class, 'resource_id');
    }

    public function stops()
    {
        return $this->hasMany(PlatformTripStop::class, 'trip_id')->orderBy('position');
    }
}
