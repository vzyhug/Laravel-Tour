<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'booking_id';

    protected $fillable = ['booking_code', 'customer_id', 'departure_id', 'total_adults', 'total_children', 'total_amount', 'status', 'created_by_employee', 'pickup_type', 'pickup_address', 'pickup_note', 'cancelled_at', 'cancel_reason', 'refund_percent', 'refund_amount', 'coupon_id', 'discount_amount', 'hold_expires_at', 'consultation_id', 'quote_snapshot'];

    protected function casts(): array
    {
        return ['quote_snapshot' => 'array', 'hold_expires_at' => 'datetime', 'booking_date' => 'datetime', 'cancelled_at' => 'datetime', 'total_amount' => 'decimal:2', 'refund_amount' => 'decimal:2', 'discount_amount' => 'decimal:2'];
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'booking_id');
    }

    public function canReview(): bool
    {
        return $this->status === 'completed'
            && $this->departure->status !== 'cancelled'
            && $this->departure->operation_status === 'completed'
            && $this->payment()->where('status', 'completed')->exists()
            && PassengerAttendance::where('status', 'present')
                ->whereIn('passenger_id', $this->passengers()->select('passenger_id'))->exists();
    }

    public function holdHasExpired(): bool
    {
        return $this->hold_expires_at !== null && $this->hold_expires_at->lte(now());
    }

    public function changes(): HasMany
    {
        return $this->hasMany(BookingChange::class, 'booking_id')->latest('id');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(ItineraryConsultation::class, 'consultation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'departure_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(BookingPassenger::class, 'booking_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'booking_id');
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class, 'booking_id');
    }

    public function cancelDeadline(): Carbon
    {
        return $this->departure->departure_date->copy()
            ->subDays((int) config('booking.cancel_before_days'))
            ->endOfDay();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true)
            && now()->lte($this->cancelDeadline());
    }

    public function refundPercentNow(): int
    {
        $fullRefundUntil = $this->booking_date->copy()->addHours((int) config('booking.full_refund_hours'));

        return $fullRefundUntil->isFuture() ? 100 : (int) config('booking.partial_refund_percent');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
