<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination_id', 'slug', 'name', 'description', 'address', 'phone', 'website_url', 'source_url', 'verified_on',
        'google_place_id', 'google_data_id', 'google_rating', 'google_reviews', 'latitude', 'longitude', 'google_maps_url', 'google_verified_on',
    ];

    protected function casts(): array
    {
        return [
            'verified_on' => 'date',
            'google_rating' => 'decimal:1',
            'google_reviews' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'google_verified_on' => 'date',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
