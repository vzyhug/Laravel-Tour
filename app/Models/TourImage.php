<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourImage extends Model
{
    protected $table = 'images_tour';

    public $timestamps = false;

    protected $primaryKey = 'image_id';

    protected $fillable = ['tour_id', 'img_url', 'is_thumbnail'];

    protected function casts(): array
    {
        return ['is_thumbnail' => 'boolean'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id');
    }
}
