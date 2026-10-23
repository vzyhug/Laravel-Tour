<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformInspection extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'platform_inspections';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'deposit_amount' => 'integer', 'odometer' => 'integer', 'fuel_percent' => 'integer'];
    }
}
