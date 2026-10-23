<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformQuote extends Model
{
    use HasFactory;

    protected $table = 'platform_quotes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'encrypted:array', 'snapshot' => 'array', 'total' => 'integer', 'expires_at' => 'datetime'];
    }

    public function option()
    {
        return $this->belongsTo(PlatformOption::class, 'option_id');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
