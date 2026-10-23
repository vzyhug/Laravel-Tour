<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceTransactionFactory extends Factory
{
    protected $model = ServiceTransaction::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory(), 'operation_key' => fake()->unique()->uuid(), 'type' => 'capture', 'amount' => 1000000, 'currency' => 'VND', 'occurred_at' => now()];
    }
}
