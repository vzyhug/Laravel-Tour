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

    protected $fillable = ['booking_date', 'booking_code', 'customer_id', 'departure_id', 'total_adults', 'total_children', 'total_amount', 'status', 'created_by_employee', 'pickup_type', 'pickup_address', 'pickup_note', 'cancelled_at', 'cancel_reason', 'refund_percent', 'refund_amount', 'coupon_id', 'discount_amount', 'checkout_key', 'request_hash', 'service_snapshot', 'payment_expires_at', 'expired_at'];

    protected function casts(): array
    {
        return ['booking_date' => 'datetime', 'cancelled_at' => 'datetime', 'total_amount' => 'decimal:2', 'refund_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'service_snapshot' => 'array', 'payment_expires_at' => 'datetime', 'expired_at' => 'datetime'];
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

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class, 'booking_id')->orderBy('id');
    }

    public function recordEvent(string $type, array $details = []): void
    {
        $this->events()->create(['type' => $type, 'details' => $details, 'occurred_at' => now()]);
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
        return Carbon::parse($this->service_snapshot['departure_date'] ?? $this->departure->departure_date->format('Y-m-d'), 'Asia/Ho_Chi_Minh')
            ->subDays($this->policyValue('cancel_before_days'))
            ->endOfDay();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true)
            && now()->lte($this->cancelDeadline());
    }

    public function refundPercentNow(): int
    {
        $fullRefundUntil = $this->booking_date->copy()->addHours($this->policyValue('full_refund_hours'));

        return $fullRefundUntil->isFuture() ? 100 : $this->policyValue('partial_refund_percent');
    }

    public function policyValue(string $key): int
    {
        return (int) ($this->service_snapshot['policy'][$key] ?? config('booking.'.$key));
    }

    public function serviceValue(string $key, mixed $fallback = null): mixed
    {
        return $this->service_snapshot[$key] ?? $fallback;
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
