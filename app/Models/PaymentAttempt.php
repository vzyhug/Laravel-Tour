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
}
