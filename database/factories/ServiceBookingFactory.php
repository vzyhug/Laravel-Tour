<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceQuote;
use App\Services\ServiceBookingService;
use App\Services\ServicePricingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceBookingFactory extends Factory
{
    protected $model = ServiceBooking::class;

    public function definition(): array
    {
        return ['quote_id' => ServiceQuote::factory(), 'reference' => 'FACTORY-'.Str::upper(Str::random(12)),
            'customer_id' => fn ($a) => ServiceQuote::findOrFail($a['quote_id'])->customer_id,
            'option_id' => fn ($a) => ServiceQuote::findOrFail($a['quote_id'])->option_id, 'checkout_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', Str::random(30)),
            'configuration' => fn ($a) => ServiceQuote::findOrFail($a['quote_id'])->configuration,
            'snapshot' => fn ($a) => ServiceQuote::findOrFail($a['quote_id'])->snapshot,
            'type' => fn ($a) => $a['snapshot']['type'], 'total' => fn ($a) => $a['snapshot']['total'],
            'contact' => ['name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => '0901234567'],
            'travelers' => [['full_name' => 'Khách demo', 'room' => 1]], 'currency' => 'VND', 'reservation_status' => 'held', 'payment_status' => 'unpaid', 'fulfillment_status' => 'pending',
            'expires_at' => now()->addMinutes(15), 'service_starts_at' => fn ($a) => Carbon::parse($a['snapshot']['starts_at']), 'service_ends_at' => fn ($a) => Carbon::parse($a['snapshot']['ends_at'])];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ServiceBooking $order) {
            $price = app(ServicePricingService::class)->calculate($order->option, $order->configuration);
            foreach ($price['allocations'] as $row) {
                $row['starts_at'] = Carbon::parse($row['starts_at']);
                $row['ends_at'] = Carbon::parse($row['ends_at']);
                $order->allocations()->create($row + ['resource_id' => $order->option->resource_id]);
            }
            $order->recordEvent('held', ['source' => 'factory']);
        });
    }

    public function paid(): static
    {
        return $this->afterCreating(function (ServiceBooking $order) {
            app(ServiceBookingService::class)->pay($order, ['attempt_key' => (string) Str::uuid(), 'method' => 'bank_transfer', 'result' => 'success', 'supplier_result' => 'success']);
            $order->refresh();
        });
    }
}
