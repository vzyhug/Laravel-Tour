<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformAllocation extends Model
{
    use HasFactory;

    protected $table = 'platform_allocations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'released_at' => 'datetime', 'quantity' => 'integer'];
    }

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(PlatformOrder::class, 'order_id');
    }

    public function resource()
    {
        return $this->belongsTo(PlatformResource::class, 'resource_id');
    }
}
