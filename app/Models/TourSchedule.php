<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourSchedule extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'schedule_id';

    protected $fillable = ['tour_id', 'day_number', 'title', 'activities', 'has_breakfast', 'has_lunch', 'has_dinner'];

    protected function casts(): array
    {
        return ['has_breakfast' => 'boolean', 'has_lunch' => 'boolean', 'has_dinner' => 'boolean'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id');
    }

    public function stops()
    {
        return $this->hasMany(TourItineraryStop::class, 'schedule_id')->orderBy('position');
    }
}
