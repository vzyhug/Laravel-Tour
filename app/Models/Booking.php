<?php

namespace App\Models;

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

    protected $fillable = ['booking_code', 'customer_id', 'departure_id', 'total_adults', 'total_children', 'total_amount', 'status', 'created_by_employee', 'pickup_type', 'pickup_address', 'pickup_note', 'cancelled_at', 'cancel_reason', 'refund_percent', 'refund_amount', 'coupon_id', 'discount_amount'];

    protected function casts(): array
    {
        return ['booking_date' => 'datetime', 'cancelled_at' => 'datetime', 'total_amount' => 'decimal:2', 'refund_amount' => 'decimal:2', 'discount_amount' => 'decimal:2'];
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

    public function cancelDeadline(): \Carbon\Carbon
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
