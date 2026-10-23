<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformOption extends Model
{
    use HasFactory;

    protected $table = 'platform_options';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'policy' => 'array', 'is_active' => 'boolean', 'price' => 'integer', 'child_price' => 'integer'];
    }

    public function resource()
    {
        return $this->belongsTo(PlatformResource::class, 'resource_id');
    }

    public function getProductAttribute()
    {
        return $this->resource->product;
    }
}
