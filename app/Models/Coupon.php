<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'coupon_id';

    protected $fillable = ['code', 'discount_percent', 'max_discount', 'min_order', 'starts_on', 'expires_on', 'usage_limit', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'expires_on' => 'date',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'coupon_id');
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    // Trả về câu báo lỗi nếu không dùng được, null nếu dùng được
    public function check(int $customerId, float $subtotal): ?string
    {
        // Đơn đã hủy không tính là đã dùng mã (khách được dùng lại)
        $active = fn () => $this->bookings()->where('status', '!=', 'cancelled');

        if (! $this->is_active) {
            return 'Mã giảm giá hiện không được áp dụng.';
        }
        if ($this->starts_on && today()->lt($this->starts_on)) {
            return 'Mã giảm giá chưa đến ngày áp dụng.';
        }
        if ($this->expires_on && today()->gt($this->expires_on)) {
            return 'Mã giảm giá đã hết hạn.';
        }
        if ($subtotal < (float) $this->min_order) {
            return 'Đơn hàng cần đạt tối thiểu '.number_format((float) $this->min_order, 0, ',', '.').'₫ để dùng mã này.';
        }
        if ($this->usage_limit !== null && $active()->count() >= $this->usage_limit) {
            return 'Mã giảm giá đã hết lượt sử dụng.';
        }
        if ($active()->where('customer_id', $customerId)->exists()) {
            return 'Bạn đã sử dụng mã này rồi.';
        }

        return null;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = round($subtotal * $this->discount_percent / 100);

        if ($this->max_discount !== null && (float) $this->max_discount > 0) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return min($discount, $subtotal);
    }
}
