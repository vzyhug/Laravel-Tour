<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'tour_id';

    protected $fillable = ['title', 'category_id', 'province_id', 'duration_days', 'duration_nights', 'pickup_point', 'description', 'itinerary_summary', 'included_services', 'is_active', 'min_price', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_price' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'category_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourImage::class, 'tour_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TourSchedule::class, 'tour_id')->orderBy('day_number');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class, 'tour_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'tour_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->images->firstWhere('is_thumbnail', true)?->img_url ?? $this->images->first()?->img_url;
    }

        public function getAverageRatingAttribute(): float
    {
        return round((float) $this->reviews()->avg('rating'), 1);
    }

    public function getReviewTotalAttribute(): int
    {
        return $this->reviews()->count();
    }
}
