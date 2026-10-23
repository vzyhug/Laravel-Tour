<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceResource extends Model
{
    use HasFactory;

    protected $table = 'service_resources';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'is_active' => 'boolean', 'capacity' => 'integer', 'buffer_minutes' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(TravelService::class, 'product_id');
    }

    public function options()
    {
        return $this->hasMany(ServicePackage::class, 'resource_id');
    }

    public function inventoryDays()
    {
        return $this->hasMany(ServiceInventoryDay::class, 'resource_id');
    }

    public function trips()
    {
        return $this->hasMany(BusTrip::class, 'resource_id');
    }

    public function allocations()
    {
        return $this->hasMany(ServiceAllocation::class, 'resource_id');
    }
}
