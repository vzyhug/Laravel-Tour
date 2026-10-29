<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourDeparture extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'departure_id';

    protected $fillable = ['tour_id', 'departure_date', 'return_date', 'max_seats', 'available_seats', 'adult_price', 'child_price', 'guide_id', 'status', 'departure_time', 'is_private'];

    protected function casts(): array
    {
        return ['departure_date' => 'date', 'return_date' => 'date', 'adult_price' => 'decimal:2', 'child_price' => 'decimal:2', 'is_private' => 'boolean'];
    }

    public function scopePubliclyBookable(Builder $query): Builder
    {
        return $query->where('is_private', false);
    }

    public function scopeEffectiveSalesStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'open' => $query->where('status', 'open')->where('operation_status', 'scheduled')
                ->whereDate('departure_date', '>=', today())->whereDate('return_date', '>=', today())->where('available_seats', '>', 0),
            'full' => $query->whereDate('departure_date', '>=', today())->whereDate('return_date', '>=', today())
                ->where('operation_status', 'scheduled')
                ->where(fn (Builder $q) => $q->where('status', 'full')->orWhere(fn (Builder $q) => $q->where('status', 'open')->where('available_seats', '<=', 0))),
            'departed' => $query->where('status', '!=', 'cancelled')->whereDate('return_date', '>=', today())
                ->where(fn (Builder $q) => $q->whereDate('departure_date', '<', today())->orWhere('operation_status', '!=', 'scheduled')),
            'ended' => $query->where('status', '!=', 'cancelled')->whereDate('return_date', '<', today()),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    public function scopeEffectiveTripStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'cancelled' => $query->where('status', 'cancelled'),
            'completed' => $query->where('status', '!=', 'cancelled')->where('operation_status', 'completed'),
            'ended' => $query->where('status', '!=', 'cancelled')->where('operation_status', '!=', 'completed')->whereDate('return_date', '<', today()),
            'in_progress' => $query->where('status', '!=', 'cancelled')->where('operation_status', 'in_progress')->whereDate('return_date', '>=', today()),
            'scheduled' => $query->where('status', '!=', 'cancelled')->where('operation_status', 'scheduled')->whereDate('return_date', '>=', today()),
            default => $query,
        };
    }

    public function getEffectiveSalesStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->return_date->lt(today())) {
            return 'ended';
        }
        if ($this->departure_date->lt(today()) || $this->operation_status !== 'scheduled') {
            return 'departed';
        }
        if ($this->status === 'full' || (int) $this->available_seats <= 0) {
            return 'full';
        }

        return 'open';
    }

    public function getEffectiveTripStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->operation_status === 'completed') {
            return 'completed';
        }
        if ($this->return_date->lt(today())) {
            return 'ended';
        }

        return $this->operation_status === 'in_progress' ? 'in_progress' : 'scheduled';
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
            ? Carbon::parse($this->departure_time)->format('h:i A')
            : null;
    }
}
