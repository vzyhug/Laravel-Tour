<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_new_ui_preserves_coupon_pickup_payment_invoice_and_cancellation_flow(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create([
            'departure_date' => today()->addDays(15),
            'departure_time' => '06:30',
            'adult_price' => 1000000, 'child_price' => 500000,
            'max_seats' => 10, 'available_seats' => 10,
        ]);
        $coupon = $this->coupon(['max_discount' => 200000]);
        $this->withSession(['customer_id' => $customer->getKey()]);

        $this->get(route('bookings.create', $departure))->assertOk()
            ->assertSee('data-booking-form', false)->assertSee($coupon->code)
            ->assertSee('name="pickup_type"', false)->assertSee($departure->time_label);
        $this->postJson(route('coupons.check'), [
            'departure_id' => $departure->getKey(), 'adults' => 2, 'children' => 1, 'code' => ' welcome10 ',
        ])->assertOk()->assertJson(['valid' => true, 'subtotal' => 2500000, 'discount' => 200000, 'total' => 2300000]);

        $payload = $this->bookingPayload($departure);
        $payload['passengers'][] = ['full_name' => 'Nguyễn Bình', 'passenger_type' => 'adult', 'id_card' => '012345678902'];
        $payload['passengers'][] = ['full_name' => 'Nguyễn Chi', 'passenger_type' => 'child', 'date_of_birth' => today()->subYears(7)->toDateString()];
        $payload += ['coupon_code' => ' welcome10 ', 'pickup_address' => '123 Đường Mới', 'pickup_note' => 'Liên hệ trước khi đón'];
        $payload['pickup_type'] = 'door';
        $response = $this->post(route('bookings.store'), $payload);

        $booking = $customer->bookings()->firstOrFail();
        $response->assertRedirect(route('payments.create', $booking));
        $this->assertSame('2300000.00', $booking->total_amount);
        $this->assertSame('200000.00', $booking->discount_amount);
        $this->assertSame($coupon->getKey(), $booking->coupon_id);
        $this->assertSame('door', $booking->pickup_type);
        $this->assertSame('123 Đường Mới', $booking->pickup_address);
        $this->assertSame(7, $departure->fresh()->available_seats);
        $this->get(route('payments.create', $booking))->assertOk()->assertSee($coupon->code)->assertSee('2.300.000₫');
        $this->post(route('payments.store', $booking), ['payment_method' => 'bank_transfer'])
            ->assertRedirect(route('customer.bookings.show', $booking));
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->getKey(), 'amount' => 2300000, 'status' => 'completed']);
        $this->get(route('customer.bookings.show', $booking))->assertOk()
            ->assertSee('123 Đường Mới')->assertSee(route('customer.bookings.cancel', $booking), false);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('2.300.000');

        $this->post(route('customer.bookings.cancel', $booking), ['cancel_reason' => 'Thay đổi kế hoạch'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('2300000.00', $booking->fresh()->refund_amount);
        $this->assertSame('refunded', $booking->payment()->firstOrFail()->status);
        $this->assertSame(10, $departure->fresh()->available_seats);
        $this->post(route('customer.bookings.cancel', $booking), ['cancel_reason' => 'Thử hủy lần nữa'])
            ->assertSessionHasErrors('cancel');
        $this->assertSame(10, $departure->fresh()->available_seats);
        $this->get(route('payments.create', $booking))->assertRedirect(route('customer.bookings.show', $booking));
        $this->post(route('payments.store', $booking), ['payment_method' => 'cash'])->assertStatus(409);
    }

    public function test_coupon_cannot_be_reused_or_overbooked_but_cancellation_releases_it(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['adult_price' => 1000000]);
        $coupon = $this->coupon(['usage_limit' => 1]);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $payload = $this->bookingPayload($departure) + ['coupon_code' => $coupon->code];
        $this->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();
        $booking = $customer->bookings()->firstOrFail();
        $seats = $departure->fresh()->available_seats;
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('coupon_code');
        $this->assertSame($seats, $departure->fresh()->available_seats);
        $this->withSession(['customer_id' => $otherCustomer->getKey()])
            ->postJson(route('coupons.check'), [
                'departure_id' => $departure->getKey(), 'adults' => 1, 'children' => 0, 'code' => $coupon->code,
            ])->assertOk()->assertJson(['valid' => false]);
        $booking->update(['status' => 'cancelled']);
        $this->postJson(route('coupons.check'), [
            'departure_id' => $departure->getKey(), 'adults' => 1, 'children' => 0, 'code' => $coupon->code,
        ])->assertOk()->assertJson(['valid' => true]);
    }

    public function test_expired_inactive_and_minimum_order_coupons_do_not_change_inventory(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['adult_price' => 1000000]);
        $this->withSession(['customer_id' => $customer->getKey()]);
        foreach ([
            ['code' => 'EXPIRED', 'expires_on' => today()->subDay()],
            ['code' => 'INACTIVE', 'is_active' => false],
            ['code' => 'MINIMUM', 'min_order' => 2000000],
            ['code' => 'FUTURE', 'starts_on' => today()->addDay()],
        ] as $attributes) {
            $coupon = $this->coupon($attributes);
            $this->post(route('bookings.store'), $this->bookingPayload($departure) + ['coupon_code' => $coupon->code])
                ->assertSessionHasErrors('coupon_code');
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame($departure->available_seats, $departure->fresh()->available_seats);
    }

    public function test_cancellation_preserves_partial_refund_deadline_and_customer_ownership_rules(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create([
            'departure_date' => today()->addDays(15),
            'max_seats' => 10, 'available_seats' => 9, 'adult_price' => 1000000,
        ]);
        $booking = Booking::factory()->for($customer)->for($departure, 'departure')->confirmed()->create([
            'total_adults' => 1, 'total_children' => 0, 'booking_date' => now()->subHours(7),
        ]);
        Payment::factory()->for($booking)->create();
        $otherCustomer = Customer::factory()->create();
        $this->withSession(['customer_id' => $otherCustomer->getKey()])
            ->post(route('customer.bookings.cancel', $booking), ['cancel_reason' => 'Không phải đơn của tôi'])
            ->assertForbidden();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->withSession(['customer_id' => $customer->getKey()])
            ->post(route('customer.bookings.cancel', $booking), ['cancel_reason' => 'Đổi lịch'])
            ->assertSessionHasNoErrors();
        $this->assertSame('700000.00', $booking->fresh()->refund_amount);
        $this->assertSame(10, $departure->fresh()->available_seats);

        $tooLate = Booking::factory()->for($customer)->create([
            'departure_id' => TourDeparture::factory()->create(['departure_date' => today()->addDays(4)])->getKey(),
        ]);
        $this->post(route('customer.bookings.cancel', $tooLate), ['cancel_reason' => 'Quá hạn'])
            ->assertSessionHasErrors('cancel');
        $this->assertSame('pending', $tooLate->fresh()->status);
    }

    public function test_invalid_child_dates_and_adult_identity_return_validation_errors_without_a_500(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create();
        $this->withSession(['customer_id' => $customer->getKey()]);
        foreach (['not-a-date', today()->subYears(12)->toDateString()] as $date) {
            $payload = $this->bookingPayload($departure);
            $payload['passengers'][] = ['full_name' => 'Trẻ em', 'passenger_type' => 'child', 'date_of_birth' => $date];
            $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('passengers.1.date_of_birth');
        }
        $payload = $this->bookingPayload($departure);
        unset($payload['passengers'][0]['id_card']);
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('passengers.0.id_card');
        $payload = $this->bookingPayload($departure);
        $payload['pickup_type'] = 'door';
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('pickup_address');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_can_manage_coupons_and_tours_with_the_validation_fixes(): void
    {
        $admin = Employee::factory()->admin()->create();
        $tour = Tour::factory()->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin']);
        $this->get(route('admin.coupons.create'))->assertOk();
        $this->post(route('admin.coupons.store'), [
            'code' => '  autumn20 ', 'discount_percent' => 20, 'max_discount' => '500.000',
            'min_order' => '1.000.000', 'starts_on' => today()->format('d/m/Y'),
            'expires_on' => today()->addMonth()->format('d/m/Y'), 'usage_limit' => 10, 'is_active' => 1,
        ])->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', ['code' => 'AUTUMN20', 'discount_percent' => 20, 'max_discount' => 500000]);
        $this->post(route('admin.tours.store'), [
            'title' => 'Tour hợp nhất mới', 'category_id' => $tour->category_id, 'province_id' => $tour->province_id,
            'duration_days' => 3, 'duration_nights' => 2, 'min_price' => 1500000, 'is_active' => 1,
        ])->assertRedirect(route('admin.tours.index'));
        $this->assertDatabaseHas('tours', ['title' => 'Tour hợp nhất mới', 'duration_days' => 3]);
        $guide = Employee::factory()->guide()->create();
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide'])
            ->get(route('admin.coupons.index'))->assertForbidden();
    }

    public function test_departure_form_keeps_formatted_dates_prices_and_time_permissions(): void
    {
        $admin = Employee::factory()->admin()->create();
        $tour = Tour::factory()->create(['duration_days' => 3, 'duration_nights' => 2]);
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin']);
        $this->get(route('admin.departures.create'))->assertOk()->assertSee('name="time_hour"', false);
        $payload = [
            'tour_id' => $tour->getKey(), 'departure_date' => today()->addDays(15)->format('d/m/Y'),
            'return_date' => today()->addDays(17)->format('d/m/Y'),
            'max_seats' => 20, 'available_seats' => 20, 'adult_price' => '1.500.000',
            'child_price' => '750.000', 'time_hour' => 2, 'time_minute' => 30, 'time_meridiem' => 'PM', 'status' => 'open',
        ];
        $this->post(route('admin.departures.store'), $payload)->assertRedirect(route('admin.departures.index'));
        $departure = TourDeparture::firstOrFail();
        $this->assertSame('14:30', $departure->departure_time);
        $this->assertSame('1500000.00', $departure->adult_price);
        $this->get(route('tours.show', $tour))->assertOk()->assertSee('02:30 PM');
        $this->get(route('admin.departures.edit', $departure))->assertOk();

        $payload['return_date'] = today()->addDays(18)->format('d/m/Y');
        $this->post(route('admin.departures.store'), $payload)->assertSessionHasErrors('return_date');
        $payload['return_date'] = 'invalid-date';
        $this->post(route('admin.departures.store'), $payload)->assertSessionHasErrors('return_date');
        $payload['return_date'] = today()->addDays(17)->format('d/m/Y');
        $payload['time_hour'] = 8;
        $this->withSession(['employee_role' => 'Sale'])->put(route('admin.departures.update', $departure), $payload)
            ->assertRedirect(route('admin.departures.index'));
        $this->assertSame('14:30', $departure->fresh()->departure_time);
    }

    public function test_admin_booking_status_transitions_remain_available_after_merging_the_ui(): void
    {
        $admin = Employee::factory()->admin()->create();
        $booking = Booking::factory()->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin']);
        $this->get(route('admin.bookings.index'))->assertOk()->assertSee($booking->booking_code);
        $this->get(route('admin.bookings.show', $booking))->assertOk();
        $this->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->patch(route('admin.bookings.status', $booking), ['status' => 'completed'])->assertSessionHasErrors('status');
        $booking->departure->update(['departure_date' => today()->subDays(3), 'return_date' => today()->subDay()]);
        $this->patch(route('admin.bookings.status', $booking), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->get(route('payments.create', $booking))->assertRedirect(route('login'));
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_replace([
            'code' => 'WELCOME10', 'discount_percent' => 10, 'min_order' => 0, 'is_active' => true,
        ], $attributes));
    }

    private function bookingPayload(TourDeparture $departure): array
    {
        return [
            'departure_id' => $departure->getKey(), 'pickup_type' => 'self',
            'passengers' => [['full_name' => 'Nguyễn An', 'passenger_type' => 'adult', 'id_card' => '012345678901']],
        ];
    }
}
