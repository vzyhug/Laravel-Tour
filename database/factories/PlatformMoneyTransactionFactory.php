<?php

namespace Database\Factories;

use App\Models\PlatformMoneyTransaction;
use App\Models\PlatformOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformMoneyTransactionFactory extends Factory
{
    protected $model = PlatformMoneyTransaction::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory(), 'operation_key' => fake()->unique()->uuid(), 'type' => 'capture', 'amount' => 1000000, 'currency' => 'VND', 'occurred_at' => now()];
    }
}
