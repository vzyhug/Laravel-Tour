<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'bill';

    public $timestamps = false;

    protected $primaryKey = 'bill_id';

    protected $fillable = ['bill_number', 'booking_id', 'employee_id', 'tax_code', 'final_amount'];

    protected function casts(): array
    {
        return ['bill_date' => 'datetime', 'final_amount' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
