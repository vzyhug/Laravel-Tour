<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartureReport extends Model
{
    protected $fillable = ['departure_id', 'submitted_by', 'summary', 'incidents', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'departure_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by');
    }
}
