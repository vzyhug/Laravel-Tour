<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePackage extends Model
{
    use HasFactory;

    protected $table = 'service_packages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'policy' => 'array', 'is_active' => 'boolean', 'price' => 'integer', 'child_price' => 'integer'];
    }

    public function resource()
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }

    public function getProductAttribute()
    {
        return $this->resource->product;
    }
}
