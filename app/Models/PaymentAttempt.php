<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function momoSession()
    {
        return $this->hasOne(MomoPaymentSession::class, 'payment_attempt_id');
    }

    public function vnpaySession()
    {
        return $this->hasOne(VnpayPaymentSession::class, 'payment_attempt_id');
    }
}
