<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceQuote extends Model
{
    use HasFactory;

    protected $table = 'service_quotes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'encrypted:array', 'snapshot' => 'array', 'total' => 'integer', 'expires_at' => 'datetime'];
    }

    public function option()
    {
        return $this->belongsTo(ServicePackage::class, 'option_id');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
