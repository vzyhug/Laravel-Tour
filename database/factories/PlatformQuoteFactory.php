<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PlatformOption;
use App\Models\PlatformQuote;
use App\Services\PlatformPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformQuoteFactory extends Factory
{
    protected $model = PlatformQuote::class;

    public function definition(): array
    {
        return ['token' => fake()->unique()->uuid(), 'customer_id' => Customer::factory(), 'option_id' => PlatformOption::factory(),
            'configuration' => ['check_in' => today('Asia/Ho_Chi_Minh')->addDays(5)->toDateString(), 'check_out' => today('Asia/Ho_Chi_Minh')->addDays(7)->toDateString(), 'rooms' => 1, 'adults' => 1, 'children' => 0],
            'snapshot' => fn ($a) => app(PlatformPricing::class)->calculate(PlatformOption::findOrFail($a['option_id']), $a['configuration'])['snapshot'],
            'fingerprint' => fn ($a) => hash('sha256', json_encode([$a['configuration'], $a['snapshot']], JSON_THROW_ON_ERROR)),
            'total' => fn ($a) => $a['snapshot']['total'], 'expires_at' => now()->addMinutes(10)];
    }
}
