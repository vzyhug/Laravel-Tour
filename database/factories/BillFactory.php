<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Bill> */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'bill_number' => 'DEMO-INV-'.Str::upper(Str::random(16)),
            'booking_id' => Booking::factory()->confirmed(),
            'bill_date' => fn (array $a) => Booking::findOrFail($a['booking_id'])->booking_date,
            'employee_id' => null,
            'tax_code' => null,
            'final_amount' => fn (array $a) => Booking::findOrFail($a['booking_id'])->total_amount,
        ];
    }
}
