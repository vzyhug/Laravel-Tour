<?php

namespace Database\Factories;

use App\Models\ServicePaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePaymentEventFactory extends Factory
{
    protected $model = ServicePaymentEvent::class;

    public function definition(): array
    {
        return ['event_id' => fake()->unique()->uuid(), 'payment_reference' => 'DEMO-FACTORY', 'payload_digest' => hash('sha256', 'demo'), 'status' => 'received', 'occurred_at' => now(), 'received_at' => now()];
    }
}
