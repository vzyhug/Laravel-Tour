<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceInventoryDay extends Model
{
    use HasFactory;

    protected $table = 'service_inventory_days';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date', 'is_closed' => 'boolean', 'capacity' => 'integer', 'price_adjustment' => 'integer'];
    }

    public function resource()
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }
}
