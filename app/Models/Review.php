<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'review';

    protected $primaryKey = 'review_id';

    protected $fillable = ['customer_id', 'tour_id', 'feedback', 'rating'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'rating' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id');
    }
}
