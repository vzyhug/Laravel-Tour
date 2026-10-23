<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ServicePackage;
use App\Models\ServiceQuote;
use App\Services\ServicePricingService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceQuoteFactory extends Factory
{
    protected $model = ServiceQuote::class;

    public function definition(): array
    {
        return ['token' => fake()->unique()->uuid(), 'customer_id' => Customer::factory(), 'option_id' => ServicePackage::factory(),
            'configuration' => ['check_in' => today('Asia/Ho_Chi_Minh')->addDays(5)->toDateString(), 'check_out' => today('Asia/Ho_Chi_Minh')->addDays(7)->toDateString(), 'rooms' => 1, 'adults' => 1, 'children' => 0],
            'snapshot' => fn ($a) => app(ServicePricingService::class)->calculate(ServicePackage::findOrFail($a['option_id']), $a['configuration'])['snapshot'],
            'fingerprint' => fn ($a) => hash('sha256', json_encode([$a['configuration'], $a['snapshot']], JSON_THROW_ON_ERROR)),
            'total' => fn ($a) => $a['snapshot']['total'], 'expires_at' => now()->addMinutes(10)];
    }
}
