<?php

namespace Database\Factories;

use App\Models\PlatformOrder;
use App\Models\PlatformRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformRefundFactory extends Factory
{
    protected $model = PlatformRefund::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory()->paid(), 'reason' => 'Demo cancellation', 'amount' => fn ($a) => PlatformOrder::findOrFail($a['order_id'])->total, 'cancellation_status' => 'requested', 'refund_status' => 'requested'];
    }
}
