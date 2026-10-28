<?php

namespace App\Models;

use App\Notifications\CustomerResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $authPasswordName = 'password_hash';

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }

    public const UPDATED_AT = null;

    protected $primaryKey = 'customer_id';

    protected $fillable = ['email', 'password_hash', 'full_name', 'phone', 'address', 'is_active'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'is_active' => 'boolean', 'session_version' => 'integer'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(CustomerItinerary::class, 'customer_id');
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
