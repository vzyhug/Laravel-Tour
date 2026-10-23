<?php

namespace Tests\Feature;

use App\Models\BusTrip;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ServiceBooking;
use App\Models\ServicePackage;
use App\Models\ServiceQuote;
use App\Models\ServiceResource;
use App\Models\Supplier;
use App\Models\TravelService;
use App\Services\ServiceBookingService;
use App\Services\ServiceCheckoutService;
use Carbon\Carbon;
use Database\Seeders\TravelServiceCatalogSeeder;
use Database\Seeders\TravelServiceDemoBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TravelServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function option(string $type = 'stay', array $attributes = []): ServicePackage
    {
        $factory = ServiceResource::factory();
        if ($type !== 'stay') {
            $factory = $factory->{$type}();
        }

        return ServicePackage::factory()->for($factory->create(), 'resource')->create($attributes);
    }

    public function test_table_rename_can_be_rolled_back_and_reapplied_without_losing_booking_data(): void
    {
        $order = ServiceBooking::factory()->paid()->create();
        $snapshot = $order->snapshot;
        $contact = $order->contact;
        $migration = require database_path('migrations/2026_10_23_100002_rename_travel_service_tables.php');

        $migration->down();
        $this->assertSame($order->reference, DB::table('platform_orders')->value('reference'));
        $this->assertSame(1, DB::table('platform_money_transactions')->count());

        $migration->up();
        $order->refresh();
        $this->assertSame($snapshot, $order->snapshot);
        $this->assertSame($contact, $order->contact);
        $this->assertSame('captured', $order->payment_status);
        $this->assertSame(1, $order->moneyTransactions()->count());
        $this->assertNotNull($order->option->resource->product->supplier);
        $this->assertNotNull($order->voucher);
        $this->assertFalse(Schema::hasTable('platform_orders'));
    }

    private function stayConfig(): array
    {
        return ['check_in' => today('Asia/Ho_Chi_Minh')->addDays(5)->toDateString(), 'check_out' => today('Asia/Ho_Chi_Minh')->addDays(7)->toDateString(), 'rooms' => 1, 'adults' => 1, 'children' => 0];
    }

    private function payload(ServiceQuote $quote): array
    {
        $travelers = [];
        for ($i = 0; $i < app(ServiceCheckoutService::class)->travelerCount($quote); $i++) {
            $child = in_array($quote->snapshot['type'], ['stay', 'activity']) && $i >= $quote->configuration['adults'];
            $travelers[] = ['full_name' => 'Khách Demo '.$i, 'room' => ($i % ($quote->configuration['rooms'] ?? 1)) + 1,
                'date_of_birth' => today()->subYears($child ? 8 : 30)->toDateString(), 'license' => 'DEMO123456'];
        }

        return ['checkout_key' => (string) Str::uuid(), 'policy_accepted' => '1', 'contact_name' => 'Người đặt thay', 'contact_email' => 'contact@example.test', 'contact_phone' => '0901234567', 'travelers' => $travelers];
    }

    private function quote(ServicePackage $option, array $config, ?Customer $customer = null): ServiceQuote
    {
        return app(ServiceCheckoutService::class)->quote(($customer ?? Customer::factory()->create())->getKey(), $option, $config);
    }

    private function reserve(ServiceQuote $quote, ?array $payload = null): ServiceBooking
    {
        return app(ServiceCheckoutService::class)->reserve($quote->customer_id, $quote, $payload ?? $this->payload($quote));
    }

    private function pay(ServiceBooking $order, string $result = 'success', string $supplier = 'success'): void
    {
        app(ServiceBookingService::class)->pay($order, ['attempt_key' => (string) Str::uuid(), 'method' => 'bank_transfer', 'result' => $result, 'supplier_result' => $supplier]);
    }

    private function domainError(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected domain error: '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function bus(): array
    {
        $option = $this->option('bus');
        $trip = BusTrip::factory()->for($option->resource, 'resource')->create();
        for ($i = 0; $i < 3; $i++) {
            $trip->stops()->create(['position' => $i, 'name' => ['Đà Nẵng', 'Huế', 'Quảng Trị'][$i], 'address' => 'Điểm demo '.$i, 'time_at' => $trip->starts_at->copy()->addHours($i * 3)]);
        }
        $stops = $trip->stops;

        return [$option, $trip, ['trip_id' => $trip->id, 'boarding_stop_id' => $stops[0]->id, 'alighting_stop_id' => $stops[1]->id, 'seats' => ['A1']]];
    }

    private function event(ServiceBooking $order, string $result = 'success'): array
    {
        $payment = $order->payments()->latest('id')->firstOrFail();

        return ['event_id' => (string) Str::uuid(), 'payment_reference' => $payment->reference, 'amount' => $payment->amount, 'currency' => 'VND', 'result' => $result, 'supplier_result' => 'success', 'occurred_at' => now()->toIso8601String()];
    }

    private function demoCallback(array $event)
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('services.callback'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_DEMO_SIGNATURE' => hash_hmac('sha256', $body, config('travel_services.demo_callback_secret'))], $body);
    }

    public function test_stay_rates_share_every_night_of_inventory_and_retry_does_not_create_an_extra_order(): void
    {
        $option = $this->option();
        $option->resource->update(['capacity' => 1]);
        $second = ServicePackage::factory()->for($option->resource, 'resource')->create(['price' => 700000]);
        $a = $this->quote($option, $this->stayConfig());
        $b = $this->quote($second, $this->stayConfig());
        $data = $this->payload($a);
        $order = $this->reserve($a, $data);
        $retry = $this->reserve($a, $data);
        $this->assertSame($order->id, $retry->id);
        $this->assertSame(1, ServiceBooking::count());
        $this->assertSame(2, $order->allocations()->count());
        $this->assertSame(1000000, $order->total);
        $this->domainError('availability', fn () => $this->reserve($b));
        $data['contact_name'] = 'Changed';
        $this->domainError('checkout_key', fn () => $this->reserve($a, $data));
        app(ServiceBookingService::class)->cancel($order, 'Không còn nhu cầu sử dụng');
        $this->assertSame('cancelled', $order->fresh()->reservation_status);
        $this->reserve($this->quote($second, $this->stayConfig()));
        $this->assertSame(2, ServiceBooking::count());
    }

    public function test_a_single_closed_or_missing_night_rejects_the_whole_stay_and_nightly_prices_are_summed(): void
    {
        $option = $this->option();
        $config = $this->stayConfig();
        $option->resource->inventoryDays()->whereDate('day', $config['check_in'])->update(['price_adjustment' => 120000]);
        $quote = $this->quote($option, $config);
        $this->assertSame(1120000, $quote->total);
        $option->resource->inventoryDays()->whereDate('day', Carbon::parse($config['check_in'])->addDay())->update(['is_closed' => true]);
        $this->domainError('availability', fn () => $this->reserve($quote));
        $this->assertSame(0, ServiceBooking::count());
    }

    public function test_stay_room_occupancy_and_child_age_are_checked_per_room_at_checkout(): void
    {
        $option = $this->option();
        $config = array_replace($this->stayConfig(), ['rooms' => 2, 'adults' => 2]);
        $quote = $this->quote($option, $config);
        $data = $this->payload($quote);
        $data['travelers'][1]['room'] = 1;
        $this->domainError('travelers', fn () => $this->reserve($quote, $data));
        $config = array_replace($this->stayConfig(), ['adults' => 1, 'children' => 1]);
        $quote = $this->quote($option, $config);
        $data = $this->payload($quote);
        $data['travelers'][1]['date_of_birth'] = today()->subYears(15)->toDateString();
        $this->domainError('travelers.1.date_of_birth', fn () => $this->reserve($quote, $data));
        $this->domainError('adults', fn () => $this->quote($option, array_replace($this->stayConfig(), ['adults' => 4])));
    }

    public function test_quote_expiry_price_changes_and_wrong_owner_do_not_hold_inventory(): void
    {
        $option = $this->option();
        $quote = $this->quote($option, $this->stayConfig());
        $option->update(['price' => 900000]);
        $this->domainError('quote', fn () => $this->reserve($quote));
        $quote = $this->quote($option->fresh(), $this->stayConfig());
        $this->travel(11)->minutes();
        $this->domainError('quote', fn () => $this->reserve($quote));
        $this->travelBack();
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->get(route('services.quotes.show', $quote))->assertForbidden();
        $this->assertSame(0, ServiceBooking::count());
    }

    public function test_bus_seats_can_be_reused_on_non_overlapping_segments_but_not_overlapping_segments(): void
    {
        [$option,$trip,$config] = $this->bus();
        $stops = $trip->stops;
        $order = $this->reserve($this->quote($option, $config));
        $this->getJson(route('services.trips.seats', [$trip, 'from' => $stops[0]->id, 'to' => $stops[1]->id]))->assertOk()->assertJsonFragment(['label' => 'A1', 'status' => 'held']);
        $next = array_replace($config, ['boarding_stop_id' => $stops[1]->id, 'alighting_stop_id' => $stops[2]->id]);
        $this->reserve($this->quote($option, $next));
        $this->domainError('availability', fn () => $this->quote($option, array_replace($config, ['alighting_stop_id' => $stops[2]->id])));
        $this->domainError('alighting_stop_id', fn () => $this->quote($option, array_replace($config, ['boarding_stop_id' => $stops[2]->id])));
        $this->assertSame(500000, $order->total);
        $this->pay($order);
        $this->getJson(route('services.trips.seats', [$trip, 'from' => $stops[0]->id, 'to' => $stops[1]->id]))->assertJsonFragment(['label' => 'A1', 'status' => 'sold']);
    }

    public function test_bus_multi_seat_request_respects_daily_capacity_as_well_as_the_seat_map(): void
    {
        [$option,$trip,$config] = $this->bus();
        $option->resource->inventoryDays()->update(['capacity' => 1]);
        $config['seats'] = ['A1', 'A2'];
        $this->domainError('availability', fn () => $this->quote($option, $config));
    }

    public function test_rental_interval_buffer_pricing_driver_rules_and_deposit_are_separate(): void
    {
        $option = $this->option('rental');
        $day = today('Asia/Ho_Chi_Minh')->addDays(5);
        $config = ['pickup_at' => $day->copy()->setTime(9, 0)->format('Y-m-d\TH:i'), 'return_at' => $day->copy()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'), 'passengers' => 2];
        $quote = $this->quote($option, $config);
        $this->assertSame(1000000, $quote->total);
        $this->assertSame(2000000, $quote->snapshot['extra']['deposit_due_at_pickup']);
        $data = $this->payload($quote);
        $data['travelers'][0]['date_of_birth'] = today()->subYears(18)->toDateString();
        $this->domainError('travelers', fn () => $this->reserve($quote, $data));
        $order = $this->reserve($quote);
        $conflict = ['pickup_at' => $day->copy()->addDay()->setTime(10, 30)->format('Y-m-d\TH:i'), 'return_at' => $day->copy()->addDays(2)->setTime(10, 30)->format('Y-m-d\TH:i'), 'passengers' => 1];
        $this->domainError('availability', fn () => $this->quote($option, $conflict));
        $this->pay($order);
        $this->travelTo($order->service_starts_at->copy()->addMinute());
        app(ServiceBookingService::class)->inspect($order, ['phase' => 'handover', 'odometer' => 100, 'fuel_percent' => 100, 'condition_note' => 'Bình thường, dữ liệu demo']);
        $this->assertSame(1000000, (int) $order->moneyTransactions()->where('type', 'capture')->sum('amount'));
        $this->assertSame(2000000, (int) $order->moneyTransactions()->where('type', 'deposit_hold')->sum('amount'));
        app(ServiceBookingService::class)->inspect($order, ['phase' => 'return', 'odometer' => 120, 'fuel_percent' => 100, 'condition_note' => 'Đã kiểm tra trả xe']);
        $this->assertSame('completed', $order->fresh()->reservation_status);
        $this->assertSame(2000000, (int) $order->moneyTransactions()->where('type', 'deposit_release')->sum('amount'));
        $this->assertTrue($order->allocations()->first()->ends_at->isFuture());
    }

    public function test_transfer_checks_zone_bags_vehicle_pool_and_encrypts_address_and_contact(): void
    {
        $option = $this->option('transfer');
        $option->resource->update(['capacity' => 1]);
        $config = ['pickup_at' => today('Asia/Ho_Chi_Minh')->addDays(5)->setTime(9, 0)->format('Y-m-d\TH:i'), 'direction' => 'airport_to_address', 'zone' => 'Sơn Trà', 'address' => 'Địa chỉ riêng để kiểm tra mã hóa', 'passengers' => 2, 'bags' => 1];
        $this->domainError('zone', fn () => $this->quote($option, array_replace($config, ['zone' => 'Ngoài vùng'])));
        $this->domainError('passengers', fn () => $this->quote($option, array_replace($config, ['bags' => 3])));
        $quote = $this->quote($option, $config);
        $this->assertSame(550000, $quote->total);
        $order = $this->reserve($quote);
        $this->domainError('availability', fn () => $this->quote($option, $config));
        $raw = DB::table('service_bookings')->find($order->id);
        $this->assertStringNotContainsString('contact@example.test', $raw->contact);
        $this->assertStringNotContainsString($config['address'], $raw->configuration);
    }

    public function test_activity_redemption_is_time_scoped_atomic_and_idempotent(): void
    {
        $option = $this->option('activity');
        $config = ['visit_date' => today('Asia/Ho_Chi_Minh')->addDays(5)->toDateString(), 'slot' => '08:00', 'adults' => 1, 'children' => 1];
        $order = $this->reserve($this->quote($option, $config));
        $this->pay($order);
        $this->domainError('voucher', fn () => app(ServiceBookingService::class)->redeem($order, 1, (string) Str::uuid()));
        $this->travelTo($order->service_starts_at->copy()->addMinute());
        $key = (string) Str::uuid();
        app(ServiceBookingService::class)->redeem($order, 1, $key);
        app(ServiceBookingService::class)->redeem($order, 1, $key);
        $this->assertSame(1, $order->voucher()->first()->remaining_uses);
        $this->domainError('voucher', fn () => app(ServiceBookingService::class)->redeem($order, 2, (string) Str::uuid()));
        app(ServiceBookingService::class)->redeem($order, 1, (string) Str::uuid());
        $this->assertSame('completed', $order->fresh()->reservation_status);
        $this->assertSame(2, $order->events()->where('type', 'voucher_redeemed')->count());
    }

    public function test_http_checkout_payment_failure_retry_documents_and_customer_isolation(): void
    {
        $customer = Customer::factory()->create();
        $option = $this->option();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->get(route('services.products.show', $option->product))->assertOk()->assertSee('Kiểm tra giá');
        $this->post(route('services.quotes.store', $option), $this->stayConfig())->assertRedirect();
        $quote = ServiceQuote::sole();
        $this->get(route('services.quotes.show', $quote))->assertOk()->assertSee('Người liên hệ');
        $this->post(route('services.orders.store', $quote), $this->payload($quote))->assertRedirect();
        $order = ServiceBooking::sole();
        $this->get(route('services.orders.show', $order))->assertOk()->assertSee('service-payment-form', false);
        $this->get(route('services.orders.document', [$order, 'voucher']))->assertNotFound();
        $this->pay($order, 'failed');
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->pay($order);
        $this->get(route('services.orders.document', [$order, 'voucher']))->assertOk()->assertDontSee('DEMO123456');
        $this->get(route('services.orders.document', [$order, 'receipt']))->assertOk()->assertSee('Thu dịch vụ');
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()]);
        $this->get(route('services.orders.show', $order))->assertForbidden();
        $this->get(route('services.orders.document', [$order, 'receipt']))->assertForbidden();
        $this->post(route('services.orders.cancel', $order), ['reason' => 'Unauthorized'])->assertForbidden();
    }

    public function test_signed_callbacks_verify_amount_currency_and_replay_does_not_issue_twice(): void
    {
        $order = $this->reserve($this->quote($this->option(), $this->stayConfig()));
        $this->pay($order, 'pending');
        $this->postJson(route('services.callback'), $this->event($order))->assertForbidden();
        $bad = $this->event($order);
        $bad['amount'] = 1;
        $this->demoCallback($bad)->assertOk();
        $this->assertSame(0, $order->moneyTransactions()->count());
        $bad = $this->event($order);
        $bad['currency'] = 'USD';
        $this->demoCallback($bad)->assertOk();
        $this->assertSame(0, $order->moneyTransactions()->count());
        $event = $this->event($order);
        for ($i = 0; $i < 3; $i++) {
            $this->demoCallback($event)->assertOk();
        }
        $this->demoCallback($this->event($order, 'failed'))->assertOk();
        $this->assertSame('captured', $order->fresh()->payment_status);
        $this->assertSame(1, $order->moneyTransactions()->where('type', 'capture')->count());
        $this->assertSame(1, $order->voucher()->count());
    }

    public function test_expiry_releases_once_and_late_money_creates_compensation_without_a_voucher(): void
    {
        $order = $this->reserve($this->quote($this->option(), $this->stayConfig()));
        $this->pay($order, 'pending');
        $this->travel(16)->minutes();
        $this->artisan('services:expire')->assertSuccessful();
        $this->assertFalse(app(ServiceBookingService::class)->expire($order));
        $this->demoCallback($this->event($order))->assertOk();
        $this->assertSame('captured', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->reservation_status);
        $this->assertNull($order->voucher);
        $this->assertSame($order->total, $order->refund()->first()->amount);
        $this->assertSame('approved', $order->refund()->first()->refund_status);
        app(ServiceBookingService::class)->refund($order);
        app(ServiceBookingService::class)->refund($order);
        $this->assertSame(1, $order->moneyTransactions()->where('type', 'refund')->count());
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_supplier_unknown_keeps_paid_inventory_and_requires_confirmation_before_issuing(): void
    {
        $option = $this->option();
        $order = $this->reserve($this->quote($option, $this->stayConfig()));
        $this->pay($order, 'success', 'unknown');
        $this->assertNull($order->voucher);
        $this->assertSame('confirming', $order->fresh()->reservation_status);
        $this->travel(16)->minutes();
        $this->assertFalse(app(ServiceBookingService::class)->expire($order));
        app(ServiceBookingService::class)->resolve($order, 'success');
        $this->assertSame('confirmed', $order->fresh()->reservation_status);
        $this->assertNotNull($order->fresh()->voucher);
    }

    public function test_cancellation_approval_and_refund_use_the_snapshot_and_do_not_release_or_refund_twice(): void
    {
        $option = $this->option('stay', ['policy' => ['cancel_allowed' => true, 'cancel_before_hours' => 24, 'refund_percent' => 80]]);
        $order = $this->reserve($this->quote($option, $this->stayConfig()));
        $this->pay($order);
        $option->update(['price' => 99000000, 'policy' => ['cancel_allowed' => false, 'refund_percent' => 0]]);
        app(ServiceBookingService::class)->cancel($order, 'Thay đổi kế hoạch sử dụng');
        $this->assertSame('cancellation_pending', $order->fresh()->reservation_status);
        $this->assertSame(0, $order->allocations()->whereNotNull('released_at')->count());
        app(ServiceBookingService::class)->decideCancellation($order, true, 'Đã xác nhận hủy demo');
        $this->assertSame(800000, $order->refund()->first()->amount);
        $this->assertSame(2, $order->allocations()->whereNotNull('released_at')->count());
        $sale = Employee::factory()->sale()->create();
        $this->withSession(['employee_id' => $sale->getKey(), 'employee_role' => 'Sale'])->post(route('admin.services.orders.action', $order), ['action' => 'refund'])->assertForbidden();
        app(ServiceBookingService::class)->refund($order);
        app(ServiceBookingService::class)->refund($order);
        $this->assertSame(800000, (int) $order->moneyTransactions()->where('type', 'refund')->sum('amount'));
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->withSession(['customer_id' => $order->customer_id])->get(route('services.orders.document', [$order, 'voucher']))->assertNotFound();
    }

    public function test_overpayment_is_recorded_and_refunded_without_a_second_voucher(): void
    {
        $order = $this->reserve($this->quote($this->option(), $this->stayConfig()));
        $this->pay($order, 'failed');
        $old = $this->event($order);
        $this->pay($order);
        $this->demoCallback($old)->assertOk();
        $this->assertSame('overpaid', $order->fresh()->reconciliation_status);
        $this->assertSame(2, $order->moneyTransactions()->where('type', 'capture')->count());
        app(ServiceBookingService::class)->refundOverpayment($order);
        app(ServiceBookingService::class)->refundOverpayment($order);
        $this->assertSame($order->total, (int) $order->moneyTransactions()->where('type', 'overpayment_refund')->sum('amount'));
        $this->assertSame(1, $order->voucher()->count());
    }

    public function test_seed_is_repeatable_preserves_admin_changes_and_provides_all_service_scenarios(): void
    {
        Customer::factory()->create(['email' => 'demo01@vietgo.test']);
        $this->seed(TravelServiceCatalogSeeder::class);
        $this->seed(TravelServiceDemoBookingSeeder::class);
        $this->assertSame(13, TravelService::count());
        $this->assertSame(9, ServiceBooking::count());
        $option = ServicePackage::first();
        $option->update(['price' => 777777]);
        $count = DB::table('service_inventory_days')->count();
        $this->seed(TravelServiceCatalogSeeder::class);
        $this->seed(TravelServiceDemoBookingSeeder::class);
        $this->assertSame(9, ServiceBooking::count());
        $this->assertSame($count, DB::table('service_inventory_days')->count());
        $this->assertSame(777777, $option->fresh()->price);
        foreach (TravelService::all() as $product) {
            $this->get(route('services.products.show', $product))->assertOk();
        }
        foreach (['stay', 'bus', 'transfer', 'rental', 'activity'] as $type) {
            $this->get(route('services.index', $type))->assertOk();
        }
    }

    public function test_admin_can_create_and_edit_a_sellable_catalog_and_reopen_bus_trips_safely(): void
    {
        $admin = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin']);
        $this->post(route('admin.services.suppliers.store'), ['code' => 'catalog-demo', 'name' => 'Nhà cung cấp thử nghiệm'])->assertSessionHasNoErrors();
        $supplier = Supplier::where('code', 'catalog-demo')->sole();
        $this->post(route('admin.services.products.store'), ['supplier_id' => $supplier->id, 'type' => 'stay', 'name' => 'Lưu trú thử nghiệm', 'location' => 'Đà Nẵng', 'description' => 'Sản phẩm phục vụ kiểm thử', 'is_active' => 1])->assertSessionHasNoErrors();
        $product = TravelService::where('supplier_id', $supplier->id)->sole();
        $this->post(route('admin.services.resources.store', $product), ['name' => 'Phòng đôi', 'capacity' => 2, 'buffer_minutes' => 0, 'is_active' => 1])->assertSessionHasNoErrors();
        $resource = $product->resources()->sole();
        $this->post(route('admin.services.options.store', $product), ['resource_id' => $resource->id, 'code' => 'catalog-rate', 'name' => 'Giá tiêu chuẩn', 'price' => 500000, 'max_adults' => 2, 'max_children' => 1, 'occupancy' => 3, 'cancel_before_hours' => 24, 'refund_percent' => 80, 'child_min_age' => 0, 'child_max_age' => 11, 'terms' => 'Điều kiện thử nghiệm', 'is_active' => 1, 'cancel_allowed' => 1])->assertSessionHasNoErrors();
        $config = $this->stayConfig();
        $this->post(route('admin.services.calendar.store', $resource), ['day_from' => $config['check_in'], 'day_to' => $config['check_out'], 'capacity' => 2, 'price_adjustment' => 10000])->assertSessionHasNoErrors();
        $quote = $this->quote($resource->options()->sole(), $config);
        $this->assertSame(1020000, $quote->total);
        $this->reserve($quote);
        $this->post(route('admin.services.resources.store', $product), ['resource_id' => $resource->id, 'name' => 'Phòng đôi', 'capacity' => 1, 'buffer_minutes' => 0, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.services.resources.store', $product), ['resource_id' => $resource->id, 'name' => 'Phòng đôi', 'capacity' => 2, 'buffer_minutes' => 0, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->reserve($this->quote($resource->options()->sole(), $config));
        $this->post(route('admin.services.resources.store', $product), ['resource_id' => $resource->id, 'name' => 'Phòng đôi', 'capacity' => 1, 'buffer_minutes' => 0, 'is_active' => 1])->assertSessionHasErrors('capacity');
        [$option,$trip] = $this->bus();
        $this->patch(route('admin.services.trips.update', $trip), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->patch(route('admin.services.trips.update', $trip), ['is_active' => 1])->assertSessionHasNoErrors();
        $other = BusTrip::factory()->for($trip->resource, 'resource')->create(['starts_at' => $trip->starts_at, 'ends_at' => $trip->ends_at]);
        $this->patch(route('admin.services.trips.update', $trip), ['is_active' => 1])->assertSessionHasErrors('trip');
    }

    public function test_paid_order_factory_returns_current_state_and_admin_rejects_fractional_redemption(): void
    {
        $order = ServiceBooking::factory()->paid()->create();
        $this->assertSame('confirmed', $order->reservation_status);
        $this->assertSame('captured', $order->payment_status);
        $admin = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin'])->post(route('admin.services.orders.action', $order), ['action' => 'redeem', 'quantity' => 1.5, 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
    }

    public function test_admin_catalog_and_inventory_pages_are_authorized_and_booked_inventory_cannot_be_reduced(): void
    {
        $option = $this->option();
        $order = $this->reserve($this->quote($option, $this->stayConfig()));
        $guide = Employee::factory()->guide()->create();
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide'])->get(route('admin.services.products.index'))->assertForbidden();
        $admin = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin']);
        $this->get(route('admin.services.products.index'))->assertOk();
        $this->get(route('admin.services.products.edit', $option->product))->assertOk();
        $this->get(route('admin.services.orders.index'))->assertOk();
        $this->get(route('admin.services.orders.show', $order))->assertOk();
        $this->post(route('admin.services.calendar.store', $option->resource), ['day_from' => $this->stayConfig()['check_in'], 'day_to' => $this->stayConfig()['check_out'], 'capacity' => 0, 'price_adjustment' => 0])->assertSessionHasErrors('capacity');
        $this->get(route('services.index', ['type' => 'stay', 'check_in' => 'not-a-date']))->assertSessionHasErrors('check_in');
    }
}
