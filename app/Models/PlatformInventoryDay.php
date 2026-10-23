<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformInventoryDay extends Model
{
    use HasFactory;

    protected $table = 'platform_inventory_days';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date', 'is_closed' => 'boolean', 'capacity' => 'integer', 'price_adjustment' => 'integer'];
    }

    public function resource()
    {
        return $this->belongsTo(PlatformResource::class, 'resource_id');
    }
}
