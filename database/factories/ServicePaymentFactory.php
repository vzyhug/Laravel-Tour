<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePaymentFactory extends Factory
{
    protected $model = ServicePayment::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory(), 'attempt_key' => fake()->unique()->uuid(), 'request_hash' => hash('sha256', 'factory'), 'reference' => 'DEMO-'.fake()->unique()->uuid(), 'provider' => 'demo', 'method' => 'bank_transfer', 'status' => 'pending', 'amount' => fn ($a) => ServiceBooking::findOrFail($a['order_id'])->total, 'currency' => 'VND'];
    }
}
