<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReconciliation extends Model
{
    protected $guarded = [];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
