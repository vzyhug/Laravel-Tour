<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIntent extends Model
{
    use HasUlids;

    protected $fillable = ['booking_id', 'reference', 'provider', 'amount', 'currency', 'status', 'qr_payload', 'expires_at', 'paid_at', 'provider_transaction_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expires_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
