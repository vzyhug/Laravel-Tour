<?php

namespace Database\Factories;

use App\Models\PlatformPaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformPaymentEventFactory extends Factory
{
    protected $model = PlatformPaymentEvent::class;

    public function definition(): array
    {
        return ['event_id' => fake()->unique()->uuid(), 'payment_reference' => 'DEMO-FACTORY', 'payload_digest' => hash('sha256', 'demo'), 'status' => 'received', 'occurred_at' => now(), 'received_at' => now()];
    }
}
