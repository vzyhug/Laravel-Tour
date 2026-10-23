<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformVoucher extends Model
{
    use HasFactory;

    protected $table = 'platform_vouchers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_from' => 'datetime', 'valid_until' => 'datetime', 'remaining_uses' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(PlatformOrder::class, 'order_id');
    }
}
