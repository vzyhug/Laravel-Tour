<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VnpayPaymentSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_vnd' => 'integer',
            'vnp_amount' => 'integer',
            'gateway_response' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }
}
