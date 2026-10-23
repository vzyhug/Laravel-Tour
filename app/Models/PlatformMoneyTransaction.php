<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformMoneyTransaction extends Model
{
    use HasFactory;

    protected $table = 'platform_money_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'datetime'];
    }

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(PlatformOrder::class, 'order_id');
    }
}
