<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourDeparture extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'departure_id';

    protected $fillable = ['tour_id', 'departure_date', 'return_date', 'max_seats', 'available_seats', 'adult_price', 'child_price', 'guide_id', 'status', 'departure_time'];

    protected function casts(): array
    {
        return ['departure_date' => 'date', 'return_date' => 'date', 'adult_price' => 'decimal:2', 'child_price' => 'decimal:2'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id');
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guide_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'departure_id');
    }

    public function getTimeLabelAttribute(): ?string
    {
        return $this->departure_time
            ? \Carbon\Carbon::parse($this->departure_time)->format('h:i A')
            : null;
    }
}
