<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'payment_id';

    protected $fillable = ['booking_id', 'amount', 'payment_method', 'transaction_code', 'status', 'payment_date'];

    protected function casts(): array
    {
        return ['payment_date' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function attempts()
    {
        return $this->hasMany(PaymentAttempt::class, 'payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    public function reconciliations()
    {
        return $this->hasMany(PaymentReconciliation::class, 'payment_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
