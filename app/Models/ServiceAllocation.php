<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAllocation extends Model
{
    use HasFactory;

    protected $table = 'service_allocations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'released_at' => 'datetime', 'quantity' => 'integer'];
    }

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(ServiceBooking::class, 'order_id');
    }

    public function resource()
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }
}
