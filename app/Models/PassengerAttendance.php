<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassengerAttendance extends Model
{
    protected $fillable = ['passenger_id', 'status', 'checked_by', 'checked_at', 'notes'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(BookingPassenger::class, 'passenger_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'checked_by');
    }
}
