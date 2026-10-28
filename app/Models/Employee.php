<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'employee_id';

    protected $fillable = ['username', 'password_hash', 'full_name', 'email', 'phone', 'role_id', 'is_active'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function guidedDepartures(): HasMany
    {
        return $this->hasMany(TourDeparture::class, 'guide_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
