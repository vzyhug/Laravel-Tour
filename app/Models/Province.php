<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'province_id';

    protected $fillable = ['province_name', 'region'];

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'province_id');
    }
}
