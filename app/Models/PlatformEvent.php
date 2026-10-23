<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformEvent extends Model
{
    use HasFactory;

    protected $table = 'platform_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_at' => 'datetime'];
    }

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(PlatformOrder::class, 'order_id');
    }
}
