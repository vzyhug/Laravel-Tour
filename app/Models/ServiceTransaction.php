<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTransaction extends Model
{
    use HasFactory;

    protected $table = 'service_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'datetime'];
    }

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(ServiceBooking::class, 'order_id');
    }
}
