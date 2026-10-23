<?php

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformOrder extends Model
{
    use HasFactory;

    protected $table = 'platform_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'encrypted:array', 'snapshot' => 'array', 'contact' => 'encrypted:array', 'travelers' => 'encrypted:array', 'total' => 'integer', 'expires_at' => 'datetime', 'service_starts_at' => 'datetime', 'service_ends_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function option()
    {
        return $this->belongsTo(PlatformOption::class, 'option_id');
    }

    public function allocations()
    {
        return $this->hasMany(PlatformAllocation::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(PlatformPayment::class, 'order_id');
    }

    public function events()
    {
        return $this->hasMany(PlatformEvent::class, 'order_id')->orderBy('id');
    }

    public function voucher()
    {
        return $this->hasOne(PlatformVoucher::class, 'order_id');
    }

    public function refund()
    {
        return $this->hasOne(PlatformRefund::class, 'order_id');
    }

    public function moneyTransactions()
    {
        return $this->hasMany(PlatformMoneyTransaction::class, 'order_id');
    }

    public function recordEvent(string $type, array $details = [], ?string $key = null): void
    {
        $this->events()->create(['type' => $type, 'details' => $details, 'operation_key' => $key, 'occurred_at' => now()]);
    }

    public function inspections()
    {
        return $this->hasMany(PlatformInspection::class, 'order_id');
    }

    public function releaseInventory(): void
    {
        $this->allocations()->whereNull('released_at')->update(['released_at' => now()]);
    }

    public function getTypeLabelAttribute(): string
    {
        return ServiceType::from($this->type)->label();
    }
}
