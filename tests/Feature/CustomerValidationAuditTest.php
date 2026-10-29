<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerItinerary;
use App\Models\Employee;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Models\HotelRoomType;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Services\Payments\PaymentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerValidationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.checkout_methods' => ['sepay', 'vietqr_demo', 'bank_transfer', 'momo', 'cash', 'card']]);
        $this->withoutVite();
        Notification::fake();
    }

    private function customer(): Customer
    {
        $customer = Customer::factory()->create();
        $this->withSession(['customer_id' => $customer->getKey(), 'customer_session_version' => $customer->session_version]);

        return $customer;
    }

    private function bookingInput(TourDeparture $departure): array
    {
        return ['departure_id' => $departure->getKey(), 'pickup_type' => 'self',
            'passengers' => [['full_name' => 'Nguyễn An', 'id_card' => '012345678901']]];
    }

    private function plan(Customer $customer): array
    {
        $tour = Tour::factory()->create();
        $tour->schedules()->create(['day_number' => 1, 'title' => 'Ngày 1']);
        $plan = CustomerItinerary::create(['customer_id' => $customer->getKey(), 'tour_id' => $tour->getKey(), 'version' => 1]);
        $plan->items()->create(['day_number' => 1, 'position' => 1, 'title' => 'Tham quan']);

        return [$tour, $plan];
    }

    public function test_public_search_rejects_malformed_filters_instead_of_crashing_or_silently_ignoring_them(): void
    {
        foreach ([['q' => ['x']], ['q' => str_repeat('x', 201)], ['category' => 'abc'], ['category' => 999999], ['days' => -1], ['days' => ['2']], ['sort' => ['price_asc']], ['sort' => 'unknown'], ['page' => 0], ['page' => ['1']]] as $query) {
            $key = array_key_first($query);
            $this->getJson(route('tours.index', $query))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        foreach ([['q' => ['x']], ['province' => 'abc']] as $query) {
            $this->getJson(route('home', $query))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($query));
        }
        foreach (['destinations.index', 'places.index', 'hotels.index'] as $route) {
            $this->getJson(route($route, ['q' => ['x']]))->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->getJson(route($route, ['page' => ['1']]))->assertUnprocessable()->assertJsonValidationErrors('page');
        }
    }

    public function test_login_and_reset_form_reject_array_and_oversized_input(): void
    {
        foreach ([['login' => ['x'], 'password' => 'Password123'], ['login' => str_repeat('a', 256), 'password' => 'Password123'], ['login' => 'a@test.test', 'password' => str_repeat('a', 1001)]] as $input) {
            $this->postJson(route('login.store'), $input)->assertUnprocessable();
        }
        $this->getJson(route('customer.password.reset', ['token' => 'abc', 'email' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('customer.password.email'), ['email' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_password_strength_is_consistent_on_register_reset_and_change_and_rejects_bcrypt_truncation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $customer = $this->customer();
        $token = Password::broker('customers')->createToken($customer);
        foreach (['abcdefgh', '12345678', str_repeat('A', 73).'1', str_repeat('Đ', 36).'1'] as $password) {
            $this->postJson(route('register.store'), ['full_name' => 'Khách mới', 'email' => 'new@example.test', 'phone' => '0901234567', 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->postJson(route('customer.password.update'), ['email' => $customer->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->putJson(route('customer.password.change.update'), ['current_password' => 'Customer@123', 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertTrue(Password::broker('customers')->tokenExists($customer, $token));
    }

    public function test_customer_login_clears_employee_identity_and_normalizes_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'customer@example.test', 'password_hash' => Hash::make('Password123')]);
        $this->withSession(['employee_id' => 123, 'employee_role' => 'Admin', 'employee_name' => 'Old admin']);
        $this->post(route('login.store'), ['login' => 'CUSTOMER@EXAMPLE.TEST', 'password' => 'Password123'])
            ->assertSessionHas('customer_id', $customer->getKey())->assertSessionMissing('employee_id')->assertSessionMissing('employee_role');
    }

    public function test_employee_login_clears_previous_customer_identity(): void
    {
        $customer = $this->customer();
        $employee = Employee::factory()->admin()->create(['password_hash' => Hash::make('Password123')]);
        $this->post(route('login.store'), ['login' => $employee->email, 'password' => 'Password123'])
            ->assertSessionHas('employee_id', $employee->getKey())->assertSessionMissing('customer_id');
    }

    public function test_register_and_profile_enforce_types_uniqueness_and_protect_server_owned_fields(): void
    {
        $customer = $this->customer();
        $other = Customer::factory()->create(['email' => 'existing@example.test', 'phone' => '0901234567']);
        $valid = ['full_name' => 'Nguyễn An', 'email' => $customer->email, 'phone' => $customer->phone, 'address' => 'Hà Nội'];
        foreach ([['full_name' => ['x']], ['email' => ['x']], ['phone' => ['x']], ['address' => ['x']], ['full_name' => '   '], ['phone' => 'not-phone'], ['email' => $other->email], ['phone' => '+84901234567']] as $change) {
            $this->putJson(route('customer.profile.update'), array_replace($valid, $change))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($change));
        }
        $this->put(route('customer.profile.update'), [...$valid, 'is_active' => false, 'customer_id' => $other->getKey(), 'session_version' => 99])->assertSessionHasNoErrors();
        $this->assertTrue($customer->fresh()->is_active);
        $this->assertSame(0, $customer->fresh()->session_version);
        $this->postJson(route('register.store'), ['full_name' => 'Khách', 'email' => 'EXISTING@EXAMPLE.TEST', 'phone' => '0987654321', 'password' => 'Password123', 'password_confirmation' => 'Password123'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_booking_rejects_duplicate_passports_invalid_dates_and_wrong_types_without_reserving_seats(): void
    {
        $this->customer();
        $departure = TourDeparture::factory()->create(['max_seats' => 10, 'available_seats' => 10]);
        $base = $this->bookingInput($departure);
        foreach ([['passengers' => []], ['passengers' => 'x'], ['passengers' => [['full_name' => ['bad'], 'id_card' => '012345678901']]], ['passengers' => [['full_name' => 'An', 'id_card' => 'short']]], ['passengers' => [['full_name' => 'An', 'id_card' => 'AB123456'], ['full_name' => 'Bình', 'id_card' => 'ab123456']]], ['passengers' => [['full_name' => 'An', 'id_card' => '012345678901', 'date_of_birth' => today()->addDay()->toDateString()]]], ['pickup_type' => 'door', 'pickup_address' => '   '], ['pickup_type' => ['self']], ['coupon_code' => ['bad']]] as $change) {
            $this->postJson(route('bookings.store'), array_replace($base, $change))->assertUnprocessable();
            $this->assertSame(10, $departure->fresh()->available_seats);
            $this->assertDatabaseCount('bookings', 0);
        }
    }

    public function test_hidden_tours_and_elapsed_same_day_departures_cannot_be_booked_or_have_coupons_applied(): void
    {
        $this->customer();
        $hidden = TourDeparture::factory()->create();
        $hidden->tour->update(['is_active' => false]);
        $this->travelTo(today()->setTime(12, 0));
        $pastTime = TourDeparture::factory()->create(['departure_date' => today(), 'departure_time' => '06:00']);
        foreach ([$hidden, $pastTime] as $departure) {
            $this->get(route('bookings.create', $departure))->assertNotFound();
            $this->postJson(route('bookings.store'), $this->bookingInput($departure))->assertUnprocessable();
            $this->postJson(route('coupons.check'), ['departure_id' => $departure->getKey(), 'adults' => 1, 'code' => 'TEST'])->assertNotFound();
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_consultation_requires_real_phone_valid_date_and_saved_version(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $customer = $this->customer();
        [$tour] = $this->plan($customer);
        $valid = ['version' => 1, 'request_key' => (string) Str::uuid(), 'travel_date' => today()->addMonth()->toDateString(), 'adults' => 2, 'contact_name' => 'Nguyễn An', 'contact_phone' => '0901234567', 'contact_email' => 'an@example.test'];
        foreach ([['contact_phone' => '--------'], ['contact_phone' => ['0901234567']], ['contact_name' => '   '], ['travel_date' => today()->subDay()->toDateString()], ['adults' => 0], ['adults' => 51], ['message' => ['bad']], ['contact_email' => 'bad'], ['version' => -1], ['request_key' => 'bad']] as $change) {
            $this->postJson(route('tour-itinerary.consult', $tour), array_replace($valid, $change))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($change));
        }
        $this->postJson(route('tour-itinerary.consult', $tour), [...$valid, 'version' => 2])->assertConflict();
        $this->assertDatabaseCount('itinerary_consultations', 0);
        $this->postJson(route('tour-itinerary.consult', $tour), $valid)->assertOk();
        $this->postJson(route('tour-itinerary.consult', $tour), $valid)->assertOk();
        $this->assertDatabaseCount('itinerary_consultations', 1);
    }

    public function test_custom_quote_also_rejects_duplicate_identity_documents(): void
    {
        $customer = $this->customer();
        [, $plan] = $this->plan($customer);
        $quote = $plan->consultations()->create(['request_key' => (string) Str::uuid(), 'itinerary_version' => 1, 'snapshot' => ['duration_days' => 1, 'duration_nights' => 0], 'travel_date' => today()->addMonth(), 'adults' => 2, 'children' => 0, 'contact_name' => $customer->full_name, 'contact_phone' => $customer->phone, 'contact_email' => $customer->email, 'status' => 'quoted', 'version' => 1, 'quoted_total' => 1000000, 'valid_until' => today()->addWeek()]);
        $this->postJson(route('itinerary-consultations.accept', $quote), ['version' => 1, 'terms_accepted' => true, 'passengers' => [['full_name' => 'An', 'id_card' => 'AB123456'], ['full_name' => 'Bình', 'id_card' => 'ab123456']]])->assertUnprocessable()->assertJsonValidationErrors('passengers.0.id_card');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_review_is_unavailable_during_return_day_and_feedback_is_escaped_after_the_trip(): void
    {
        $customer = $this->customer();
        $booking = Booking::factory()->for($customer)->confirmed()->create();
        $booking->departure->update(['departure_date' => today()->subDay(), 'return_date' => today()]);
        $this->travelTo(today()->setTime(12, 0));
        $this->postJson(route('customer.bookings.review', $booking), ['rating' => 5, 'feedback' => 'Chuyến đi tốt'])->assertForbidden();
        $booking->departure->update(['return_date' => today()->subDay()]);
        foreach ([['rating' => 0, 'feedback' => 'Tốt lắm'], ['rating' => [5], 'feedback' => 'Tốt lắm'], ['rating' => 5, 'feedback' => '   '], ['rating' => 5, 'feedback' => str_repeat('a', 2001)]] as $input) {
            $this->postJson(route('customer.bookings.review', $booking), $input)->assertUnprocessable();
        }
        $this->post(route('customer.bookings.review', $booking), ['rating' => 5, 'feedback' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
        $this->get(route('tours.show', $booking->departure->tour))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_invoice_keeps_the_charged_subtotal_after_departure_prices_change(): void
    {
        $customer = $this->customer();
        $booking = Booking::factory()->for($customer)->confirmed()->create(['total_amount' => 900000, 'discount_amount' => 100000, 'total_adults' => 1, 'total_children' => 0]);
        $booking->payment()->create(['amount' => 900000, 'status' => 'completed', 'payment_method' => 'cash']);
        $booking->bill()->create(['bill_number' => 'INV-AUDIT', 'final_amount' => 900000]);
        $booking->departure->update(['adult_price' => 7000000]);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('1.000.000₫')->assertDontSee('7.000.000₫')->assertSee('900.000₫');
    }

    public function test_hotel_validation_capacity_ownership_and_payment_are_guarded(): void
    {
        $customer = $this->customer();
        $hotel = Hotel::factory()->create();
        $type = HotelRoomType::create(['hotel_id' => $hotel->id, 'name' => 'Phòng kiểm thử', 'max_guests' => 2]);
        $start = today()->addDays(3)->toDateString();
        $end = today()->addDays(4)->toDateString();
        $type->inventory()->create(['stay_date' => $start, 'total_rooms' => 1, 'available_rooms' => 1, 'nightly_price' => 500000]);
        $valid = ['check_in' => $start, 'check_out' => $end, 'rooms' => 1, 'guests' => 2];
        foreach ([['check_in' => 'bad'], ['check_in' => [$start]], ['check_out' => $start], ['check_out' => today()->addDays(40)->toDateString()], ['rooms' => 0], ['rooms' => 11], ['guests' => 101], ['guests' => ['bad']]] as $change) {
            $this->getJson(route('hotel-rooms.search', [$hotel, ...array_replace($valid, $change)]))->assertUnprocessable();
        }
        $input = [...$valid, 'request_key' => (string) Str::uuid(), 'room_type_id' => $type->id];
        $this->postJson(route('hotel-bookings.store'), [...$input, 'guests' => 3])->assertUnprocessable();
        $this->post(route('hotel-bookings.store'), [...$input, 'total_amount' => 1, 'customer_id' => 999])->assertSessionHasNoErrors();
        $booking = HotelBooking::firstOrFail();
        $this->assertSame(500000, $booking->total_amount);
        $this->assertSame($customer->getKey(), $booking->customer_id);
        $this->customer();
        foreach (['hotel-bookings.pay', 'hotel-bookings.cancel'] as $route) {
            $this->postJson(route($route, $booking))->assertForbidden();
        }
        $this->getJson(route('hotel-bookings.show', $booking))->assertForbidden();
        $this->assertDatabaseCount('hotel_payments', 0);
    }

    public function test_booking_payment_invoice_cancellation_and_favorites_enforce_owner_and_state(): void
    {
        $customer = $this->customer();
        $booking = Booking::factory()->for($customer)->create(['status' => 'pending']);
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()]);
        foreach (['customer.bookings.show', 'customer.bookings.invoice', 'payments.create', 'customer.booking-changes.edit'] as $route) {
            $this->getJson(route($route, $booking))->assertForbidden();
        }
        foreach (['payments.store', 'customer.bookings.cancel', 'customer.bookings.review'] as $route) {
            $this->postJson(route($route, $booking), [])->assertForbidden();
        }
        $this->withSession(['customer_id' => $customer->getKey()]);
        foreach ([['payment_method' => ['cash']], ['payment_method' => 'invalid'], ['payment_method' => 'cash', 'demo_outcome' => 'invalid'], ['payment_method' => 'cash', 'demo_outcome' => 'succeeded', 'idempotency_key' => 'bad']] as $input) {
            $this->postJson(route('payments.store', $booking), $input)->assertUnprocessable();
        }
        $this->postJson(route('customer.bookings.cancel', $booking), ['cancel_reason' => '   '])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('favorites.toggle', $booking->departure->tour))->assertRedirect();
        $this->get(route('favorites.index'))->assertOk()->assertSee($booking->departure->tour->title);
        $this->post(route('favorites.toggle', $booking->departure->tour))->assertRedirect();
        $this->assertSame(0, $customer->favoriteTours()->count());
        $booking->departure->tour->update(['is_active' => false]);
        $this->post(route('favorites.toggle', $booking->departure->tour))->assertNotFound();
    }

    public function test_invalid_old_input_never_breaks_registration_profile_or_passenger_forms(): void
    {
        $customer = $this->customer();
        $bad = ['full_name' => ['x'], 'email' => ['x'], 'phone' => ['x'], 'address' => ['x'], 'login' => ['x'], 'pickup_note' => ['x'], 'coupon_code' => ['x'], 'passengers' => [['full_name' => ['x'], 'id_card' => ['x'], 'date_of_birth' => ['x']]]];
        foreach (['register', 'login', 'customer.profile', 'customer.password.request'] as $route) {
            $this->withSession(['_old_input' => $bad])->get(route($route))->assertOk();
        }
        $departure = TourDeparture::factory()->create();
        $this->withSession(['_old_input' => $bad])->get(route('bookings.create', $departure))->assertOk();
    }

    public function test_guest_cannot_use_customer_endpoints_and_disabled_sessions_are_revoked(): void
    {
        $customer = Customer::factory()->create();
        $booking = Booking::factory()->for($customer)->create();
        $tour = $booking->departure->tour;
        foreach (['customer.profile', 'customer.dashboard', 'favorites.index', 'tour-itinerary.index', 'hotel-bookings.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        foreach ([['bookings.store', [], []], ['coupons.check', [], []], ['favorites.toggle', [$tour], []], ['tour-itinerary.update', [$tour], ['version' => 0]], ['hotel-bookings.store', [], []]] as [$route, $params, $input]) {
            $method = $route === 'tour-itinerary.update' ? 'put' : 'post';
            $this->$method(route($route, $params), $input)->assertRedirect(route('login'));
        }
        $customer->update(['is_active' => false]);
        $this->withSession(['customer_id' => $customer->getKey()])->get(route('customer.profile'))->assertRedirect(route('login'))->assertSessionMissing('customer_id');
    }

    public function test_vnpay_reference_array_is_a_validation_error_and_does_not_crash(): void
    {
        $this->customer();
        $this->getJson(route('payments.vnpay.return', ['vnp_TxnRef' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('vnp_TxnRef');
    }

    public function test_legacy_demo_qr_refuses_expired_holds_and_retry_keeps_the_demo_method(): void
    {
        config(['payments.demo_enabled' => true]);
        $customer = $this->customer();
        $booking = Booking::factory()->for($customer)->create(['hold_expires_at' => now()->addMinute()]);
        $this->post(route('payments.store', $booking), ['payment_method' => 'vietqr_demo'])->assertRedirect();
        $intent = $booking->paymentIntents()->firstOrFail();
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'failed'])->assertRedirect();
        $this->get(route('payments.qr.show', [$booking, $intent]))->assertOk()->assertSee('name="payment_method" value="vietqr_demo"', false);
        $this->travel(2)->minutes();
        $this->postJson(route('payments.store', $booking), ['payment_method' => 'vietqr_demo'])->assertConflict();
        $this->assertDatabaseCount('payment_intents', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_registration_canonicalizes_phone_and_email_and_cannot_inject_admin_fields(): void
    {
        $this->post(route('register.store'), ['full_name' => 'Nguyễn An', 'email' => 'NEW@EXAMPLE.TEST', 'phone' => '+84901234567', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'is_active' => false, 'employee_id' => 1, 'session_version' => 99])
            ->assertRedirect(route('home'))->assertSessionHas('customer_id');
        $customer = Customer::firstOrFail();
        $this->assertSame('new@example.test', $customer->email);
        $this->assertSame('0901234567', $customer->phone);
        $this->assertTrue($customer->is_active);
        $this->assertSame(0, $customer->session_version);
        $this->assertTrue(Hash::check('Password123', $customer->password_hash));
        $this->postJson(route('register.store'), ['full_name' => 'Khách khác', 'email' => 'other@example.test', 'phone' => '0901234567', 'password' => 'Password123', 'password_confirmation' => 'Password123'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_coupon_invalid_conditions_cannot_reserve_seats_and_final_total_is_server_owned(): void
    {
        $customer = $this->customer();
        $departure = TourDeparture::factory()->create(['adult_price' => 1000000, 'max_seats' => 4, 'available_seats' => 4]);
        $coupon = Coupon::create(['code' => 'AUDIT10', 'discount_percent' => 10, 'min_order' => 0, 'is_active' => false]);
        $data = [...$this->bookingInput($departure), 'coupon_code' => 'AUDIT10'];
        foreach ([['is_active' => false], ['is_active' => true, 'expires_on' => today()->subDay()], ['expires_on' => null, 'starts_on' => today()->addDay()], ['starts_on' => null, 'min_order' => 2000000], ['min_order' => 0, 'usage_limit' => 0]] as $conditions) {
            $coupon->update($conditions);
            $this->postJson(route('bookings.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
            $this->assertSame(4, $departure->fresh()->available_seats);
        }
        $coupon->update(['usage_limit' => 1]);
        $this->post(route('bookings.store'), [...$data, 'total_amount' => 1, 'discount_amount' => 9999999, 'customer_id' => 999])->assertSessionHasNoErrors();
        $booking = Booking::firstOrFail();
        $this->assertSame($customer->getKey(), $booking->customer_id);
        $this->assertSame('900000.00', $booking->total_amount);
        $this->assertSame('100000.00', $booking->discount_amount);
        $this->postJson(route('bookings.store'), $data)->assertUnprocessable();
        $this->assertSame(3, $departure->fresh()->available_seats);
    }

    public function test_real_route_throttle_limits_login_attempts(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login.store'), ['login' => 'missing@example.test', 'password' => 'wrong'])->assertRedirect();
        }
        $this->post(route('login.store'), ['login' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_disabling_demo_payments_also_disables_cash_card_and_customer_resolution(): void
    {
        $customer = $this->customer();
        $booking = Booking::factory()->for($customer)->create();
        $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
        config(['payments.demo_enabled' => false]);
        foreach (['cash', 'card', 'vietqr_demo'] as $method) {
            $this->postJson(route('payments.store', $booking), ['payment_method' => $method, 'demo_outcome' => 'succeeded'])->assertNotFound();
        }
        $this->postJson(route('payments.demo.resolve', $attempt), ['outcome' => 'succeeded'])->assertNotFound();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_dashboard_does_not_label_pending_or_failed_payments_as_paid_and_allows_retry(): void
    {
        $customer = $this->customer();
        $booking = Booking::factory()->for($customer)->create(['hold_expires_at' => now()->addMinutes(30)]);
        $payment = $booking->payment()->create(['amount' => $booking->total_amount, 'status' => 'failed', 'payment_method' => 'card']);
        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Thanh toán thất bại')->assertDontSee('Đã thanh toán')->assertSee(route('payments.create', $booking), false);
        $payment->update(['status' => 'pending']);
        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Đang chờ xử lý')->assertDontSee('Đã thanh toán');
        $this->get(route('customer.bookings.show', $booking))->assertOk()->assertSee('Chưa có giao dịch hoàn tất');
    }

    public function test_opening_an_expired_booking_releases_its_seats_once_even_without_scheduler(): void
    {
        $customer = $this->customer();
        $departure = TourDeparture::factory()->create(['max_seats' => 10, 'available_seats' => 9]);
        $booking = Booking::factory()->for($customer)->for($departure, 'departure')->create(['total_adults' => 1, 'hold_expires_at' => now()->subSecond()]);
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('customer.bookings.show', $booking))->assertOk()->assertSee('Đã hủy');
        }
        $this->assertSame(10, $departure->fresh()->available_seats);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}
