<?php

namespace Database\Factories;

use App\Models\PlatformOrder;
use App\Models\PlatformPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformPaymentFactory extends Factory
{
    protected $model = PlatformPayment::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory(), 'attempt_key' => fake()->unique()->uuid(), 'request_hash' => hash('sha256', 'factory'), 'reference' => 'DEMO-'.fake()->unique()->uuid(), 'provider' => 'demo', 'method' => 'bank_transfer', 'status' => 'pending', 'amount' => fn ($a) => PlatformOrder::findOrFail($a['order_id'])->total, 'currency' => 'VND'];
    }
}
