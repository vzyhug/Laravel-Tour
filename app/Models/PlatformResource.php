<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformResource extends Model
{
    use HasFactory;

    protected $table = 'platform_resources';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'is_active' => 'boolean', 'capacity' => 'integer', 'buffer_minutes' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(PlatformProduct::class, 'product_id');
    }

    public function options()
    {
        return $this->hasMany(PlatformOption::class, 'resource_id');
    }

    public function inventoryDays()
    {
        return $this->hasMany(PlatformInventoryDay::class, 'resource_id');
    }

    public function trips()
    {
        return $this->hasMany(PlatformTrip::class, 'resource_id');
    }

    public function allocations()
    {
        return $this->hasMany(PlatformAllocation::class, 'resource_id');
    }
}
