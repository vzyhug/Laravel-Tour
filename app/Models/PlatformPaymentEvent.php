<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformPaymentEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'platform_payment_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'received_at' => 'datetime'];
    }
}
