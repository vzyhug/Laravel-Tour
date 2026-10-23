<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSupplier extends Model
{
    use HasFactory;

    protected $table = 'platform_suppliers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products()
    {
        return $this->hasMany(PlatformProduct::class, 'supplier_id');
    }
}
