<?php

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelService extends Model
{
    use HasFactory;

    protected $table = 'travel_services';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'is_active' => 'boolean'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function resources()
    {
        return $this->hasMany(ServiceResource::class, 'product_id');
    }

    public function options()
    {
        return $this->hasManyThrough(ServicePackage::class, ServiceResource::class, 'product_id', 'resource_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return ServiceType::from($this->type)->label();
    }

    public function getImageUrlAttribute(): string
    {
        return asset($this->image_path ?: 'images/tours/demo/da-nang-2.jpg');
    }
}
