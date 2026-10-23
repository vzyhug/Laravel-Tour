<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\PlatformOption;
use App\Models\PlatformOrder;
use App\Services\PlatformCheckout;
use App\Services\PlatformOrders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformDemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::where('email', 'demo01@vietgo.test')->first();
        if (! $customer) {
            return;
        }
        foreach ([['stay', 'held'], ['stay', 'paid'], ['bus', 'paid'], ['transfer', 'unknown'], ['rental', 'paid'], ['activity', 'paid'], ['transfer', 'supplier_failed'], ['activity', 'cancel_requested'], ['bus', 'pending']] as [$type, $scenario]) {
            $reference = 'DEMO-'.strtoupper($type.'-'.$scenario);
            if (PlatformOrder::where('reference', $reference)->exists()) {
                continue;
            }
            $option = PlatformOption::whereHas('resource.product', fn ($q) => $q->where('type', $type)->where('is_active', true))->where('is_active', true)->first();
            if (! $option) {
                continue;
            }
            $day = today('Asia/Ho_Chi_Minh')->addDays($scenario === 'held' ? 6 : 5);
            $config = match ($type) {
                'stay' => ['check_in' => $day->toDateString(), 'check_out' => $day->copy()->addDays(2)->toDateString(), 'rooms' => 1, 'adults' => 1, 'children' => 0],
                'rental' => ['pickup_at' => $day->copy()->setTime(9, 0)->format('Y-m-d\TH:i'), 'return_at' => $day->copy()->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'), 'passengers' => 2],
                'transfer' => ['pickup_at' => $day->copy()->setTime($scenario === 'unknown' ? 9 : 14, 0)->format('Y-m-d\TH:i'), 'direction' => 'airport_to_address', 'zone' => array_key_first($option->attributes['zones']), 'address' => 'Địa chỉ giả trong vùng đã chọn', 'passengers' => 2, 'bags' => 1, 'flight_number' => 'DEMO123'],
                'activity' => ['visit_date' => $day->toDateString(), 'slot' => $scenario === 'paid' ? '08:00' : '14:00', 'adults' => 1, 'children' => 0],
                'bus' => $this->busConfig($option, $scenario),
            };
            if (! $config) {
                continue;
            }
            try {
                $quote = app(PlatformCheckout::class)->quote($customer->getKey(), $option, $config);
                $traveler = ['full_name' => 'Khách mẫu VietGo', 'date_of_birth' => today()->subYears(30)->toDateString(), 'room' => 1, 'license' => 'DEMO123456'];
                $order = app(PlatformCheckout::class)->reserve($customer->getKey(), $quote, ['checkout_key' => (string) Str::uuid(), 'policy_accepted' => '1',
                    'contact_name' => 'Liên hệ demo', 'contact_email' => 'booking@vietgo.test', 'contact_phone' => '0901234567', 'travelers' => [$traveler]]);
                $order->update(['reference' => $reference]);
                if ($scenario !== 'held') {
                    app(PlatformOrders::class)->pay($order, ['attempt_key' => (string) Str::uuid(), 'method' => 'bank_transfer', 'result' => $scenario === 'pending' ? 'pending' : 'success',
                        'supplier_result' => match ($scenario) {
                            'unknown' => 'unknown', 'supplier_failed' => 'failed', default => 'success'
                        }]);
                }
                if ($scenario === 'cancel_requested') {
                    app(PlatformOrders::class)->cancel($order->fresh(), 'Yêu cầu hủy mẫu để kiểm tra phê duyệt và hoàn tiền.');
                }
            } catch (ValidationException $exception) {
                $this->command?->warn('Bỏ qua đơn mẫu '.$reference.': '.collect($exception->errors())->flatten()->first());
            }
        }
    }

    private function busConfig(PlatformOption $option, string $scenario): array
    {
        $trip = $option->resource->trips()->with('stops')->where('is_active', true)->where('starts_at', '>', now())->orderBy('starts_at')->first();
        if (! $trip) {
            return [];
        }

        return ['trip_id' => $trip->id, 'boarding_stop_id' => $trip->stops->first()->id, 'alighting_stop_id' => $trip->stops->last()->id, 'seats' => [$scenario === 'pending' ? 'B4' : 'A1']];
    }
}
