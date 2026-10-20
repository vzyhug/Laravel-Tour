<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->confirmed(),
            'payment_date' => fn (array $a) => Booking::findOrFail($a['booking_id'])->booking_date,
            'amount' => fn (array $a) => Booking::findOrFail($a['booking_id'])->total_amount,
            'payment_method' => fake()->randomElement(['bank_transfer', 'cash', 'momo', 'card']),
            'transaction_code' => 'DEMO-TXN-'.Str::upper(Str::random(16)),
            'status' => 'completed',
        ];
    }
}
