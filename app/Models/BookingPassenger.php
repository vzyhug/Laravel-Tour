<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPassenger extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'passenger_id';

    protected $fillable = ['booking_id', 'full_name', 'passenger_type', 'id_card', 'date_of_birth'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
