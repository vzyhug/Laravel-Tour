<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'region', 'description', 'source_url', 'verified_on'];

    protected function casts(): array
    {
        return ['verified_on' => 'date'];
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(ItineraryPlace::class, 'destination_itinerary_place');
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
