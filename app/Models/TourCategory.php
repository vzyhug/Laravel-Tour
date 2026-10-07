<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourCategory extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'category_id';

    protected $fillable = ['category_name', 'description'];

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'category_id');
    }
}
