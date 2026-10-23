<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformPayment extends Model
{
    use HasFactory;

    protected $table = 'platform_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(PlatformOrder::class, 'order_id');
    }
}
