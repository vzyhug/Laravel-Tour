<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryConsultation extends Model
{
    public const STATUS_LABELS = [
        'submitted' => 'Chờ tư vấn',
        'quoted' => 'Đã báo giá',
        'accepted' => 'Đã chấp nhận báo giá',
        'rejected' => 'Đã từ chối báo giá',
        'closed' => 'Đã đóng',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'travel_date' => 'date', 'valid_until' => 'date', 'quoted_total' => 'integer', 'version' => 'integer', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function itinerary()
    {
        return $this->belongsTo(CustomerItinerary::class, 'itinerary_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function booking()
    {
        return $this->hasOne(Booking::class, 'consultation_id');
    }

    public function canBeAccepted(): bool
    {
        return $this->status === 'quoted'
            && $this->quoted_total > 0
            && $this->valid_until !== null
            && $this->valid_until->gte(today())
            && $this->travel_date->copy()->setTimeFromTimeString($this->departureTime())->gt(now());
    }

    public function departureTime(): string
    {
        return $this->snapshot['departure_time'] ?? config('booking.default_departure_time', '06:00');
    }
}
