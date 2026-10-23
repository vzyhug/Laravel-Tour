<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceBookingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceBookingEventFactory extends Factory
{
    protected $model = ServiceBookingEvent::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory(), 'type' => 'held', 'details' => ['source' => 'factory'], 'occurred_at' => now()];
    }
}
