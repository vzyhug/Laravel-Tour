<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'customer_id';

    protected $fillable = ['email', 'password_hash', 'full_name', 'phone', 'address', 'is_active'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

        public function favoriteTours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'favorites', 'customer_id', 'tour_id');
    }
}
