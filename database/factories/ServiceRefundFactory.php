<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRefundFactory extends Factory
{
    protected $model = ServiceRefund::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory()->paid(), 'reason' => 'Demo cancellation', 'amount' => fn ($a) => ServiceBooking::findOrFail($a['order_id'])->total, 'cancellation_status' => 'requested', 'refund_status' => 'requested'];
    }
}
