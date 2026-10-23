<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRefund extends Model
{
    use HasFactory;

    protected $table = 'service_refunds';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(ServiceBooking::class, 'order_id');
    }
}
