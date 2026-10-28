<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Services\HotelBookingService;
use Database\Seeders\HotelBookingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HotelBookingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $capacity = 1): array
    {
        $customer = Customer::factory()->create();
        $hotel = Hotel::factory()->create();
        $type = HotelRoomType::create(['hotel_id' => $hotel->id, 'name' => 'Test room', 'max_guests' => 2]);
        foreach ([1, 2] as $offset) {
            HotelRoomInventory::create(['room_type_id' => $type->id, 'stay_date' => today()->addDays($offset), 'total_rooms' => $capacity, 'available_rooms' => $capacity, 'nightly_price' => 500000]);
        }

        return [$customer, $hotel, $type, ['request_key' => (string) Str::uuid(), 'room_type_id' => $type->id, 'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(3)->toDateString(), 'rooms' => 1, 'guests' => 2]];
    }

    public function test_hold_prices_all_nights_excludes_checkout_and_retries_without_reserving_twice(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $service = app(HotelBookingService::class);
        $first = $service->hold($customer->getKey(), $data);
        $retry = $service->hold($customer->getKey(), $data);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1000000, $first->total_amount);
        $this->assertSame(2, $first->nights()->count());
        $this->assertSame(0, (int) $type->inventory()->sum('available_rooms'));
        $this->assertDatabaseCount('hotel_bookings', 1);
    }

    public function test_next_customer_cannot_reserve_last_room_already_held(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $service = app(HotelBookingService::class);
        $service->hold($customer->getKey(), $data);
        try {
            $service->hold(Customer::factory()->create()->getKey(), [...$data, 'request_key' => (string) Str::uuid()]);
            $this->fail('Sold-out room must not be reserved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rooms', $e->errors());
        }
        $this->assertDatabaseCount('hotel_bookings', 1);
        $this->assertSame(0, (int) $type->inventory()->min('available_rooms'));
    }

    public function test_missing_night_rejects_entire_hold_without_touching_other_nights(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $type->inventory()->whereDate('stay_date', today()->addDays(2))->delete();
        try {
            app(HotelBookingService::class)->hold($customer->getKey(), $data);
            $this->fail('Missing inventory must reject hold.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('hotel_bookings', 0);
        $this->assertSame(1, (int) $type->inventory()->sum('available_rooms'));
    }

    public function test_payment_cancel_and_expiry_retries_do_not_duplicate_payment_or_release(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $service = app(HotelBookingService::class);
        $booking = $service->hold($customer->getKey(), $data);
        $this->assertTrue($service->pay($booking));
        $this->assertTrue($service->pay($booking));
        $this->assertDatabaseCount('hotel_payments', 1);
        $this->assertTrue($booking->payment()->firstOrFail()->is_simulated);
        $this->assertSame(0, (int) $type->inventory()->sum('available_rooms'));
        $service->cancel($booking);
        $service->cancel($booking);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('refunded', $booking->payment()->firstOrFail()->status);
        $this->assertSame(2, (int) $type->inventory()->sum('available_rooms'));
        $this->assertSame(0, $service->expire());
    }

    public function test_expiry_returns_inventory_once_and_late_payment_cannot_confirm(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $service = app(HotelBookingService::class);
        $booking = $service->hold($customer->getKey(), $data);
        $this->travel(16)->minutes();
        $this->assertSame(1, $service->expire());
        $this->assertSame(0, $service->expire());
        $this->assertFalse($service->pay($booking));
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(2, (int) $type->inventory()->sum('available_rooms'));
        $this->assertDatabaseCount('hotel_payments', 0);
    }

    public function test_late_payment_without_scheduler_commits_expiry_and_releases_rooms(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $service = app(HotelBookingService::class);
        $booking = $service->hold($customer->getKey(), $data);
        $this->travel(16)->minutes();
        $this->assertFalse($service->pay($booking));
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(2, (int) $type->inventory()->sum('available_rooms'));
    }

    public function test_price_changes_do_not_reprice_held_booking(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        $service = app(HotelBookingService::class);
        $booking = $service->hold($customer->getKey(), $data);
        $type->inventory()->update(['nightly_price' => 900000]);
        $service->pay($booking);
        $this->assertSame(1000000, $booking->payment()->firstOrFail()->amount);
        $this->assertSame(500000, $booking->nights()->firstOrFail()->nightly_price);
    }

    public function test_room_search_customer_checkout_and_ownership(): void
    {
        [$customer, $hotel, $type, $data] = $this->fixture();
        $this->get(route('hotel-rooms.search', $hotel).'?'.http_build_query(collect($data)->only(['check_in', 'check_out', 'rooms', 'guests'])->all()))->assertOk()->assertSee('Test room')->assertSee('mô phỏng');
        $this->withSession(['customer_id' => $customer->getKey()])->post(route('hotel-bookings.store'), $data)->assertRedirect();
        $booking = HotelBooking::firstOrFail();
        $this->get(route('hotel-bookings.show', $booking))->assertOk()->assertSee($booking->code);
        $this->post(route('hotel-bookings.pay', $booking))->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->get(route('hotel-bookings.show', $booking))->assertForbidden();
        $this->post(route('hotel-bookings.cancel', $booking))->assertForbidden();
        $this->post(route('hotel-bookings.pay', $booking))->assertForbidden();
    }

    public function test_admin_cannot_reduce_capacity_below_existing_reservations(): void
    {
        [$customer, , $type, $data] = $this->fixture();
        app(HotelBookingService::class)->hold($customer->getKey(), $data);
        $this->withSession(['employee_id' => 1, 'employee_role' => 'Admin'])->put(route('admin.hotel-booking.inventory.save', $type), ['stay_date' => $data['check_in'], 'total_rooms' => 0, 'nightly_price' => 600000])->assertSessionHasErrors('total_rooms');
        $this->assertSame(1, $type->inventory()->firstOrFail()->total_rooms);
        $this->assertSame(0, $type->inventory()->firstOrFail()->available_rooms);
    }

    public function test_customer_cannot_edit_inventory_and_admin_ui_renders(): void
    {
        [$customer, , $type] = $this->fixture();
        $this->withSession(['customer_id' => $customer->getKey()])->put(route('admin.hotel-booking.inventory.save', $type), [])->assertRedirect(route('login'));
        $this->withSession(['employee_id' => 1, 'employee_role' => 'Admin'])->get(route('admin.hotel-booking.index'))->assertOk()->assertSee('Quản trị đặt khách sạn');
        $this->get(route('admin.hotel-booking.inventory', $type))->assertOk()->assertSee('Nhập theo từng đêm');
    }

    public function test_oversized_occupancy_and_reused_key_with_changed_details_are_rejected(): void
    {
        [$customer, , , $data] = $this->fixture(2);
        $service = app(HotelBookingService::class);
        try {
            $service->hold($customer->getKey(), [...$data, 'guests' => 3]);
            $this->fail('Too many guests.');
        } catch (ValidationException) {
        }
        $service->hold($customer->getKey(), $data);
        $this->expectException(ValidationException::class);
        $service->hold($customer->getKey(), [...$data, 'rooms' => 2]);
    }

    public function test_admin_price_and_capacity_changes_preserve_allocations_and_checkout_is_excluded(): void
    {
        [$customer, , $type, $data] = $this->fixture(2);
        app(HotelBookingService::class)->hold($customer->getKey(), $data);
        $this->withSession(['employee_id' => 1, 'employee_role' => 'Admin'])->put(route('admin.hotel-booking.inventory.save', $type), ['stay_date' => $data['check_in'], 'total_rooms' => 3, 'nightly_price' => 600000])->assertSessionHasNoErrors();
        $night = $type->inventory()->whereDate('stay_date', $data['check_in'])->firstOrFail();
        $this->assertSame(3, $night->total_rooms);
        $this->assertSame(2, $night->available_rooms);
        $this->assertSame(600000, $night->nightly_price);
        $this->assertFalse($type->inventory()->whereDate('stay_date', $data['check_out'])->exists());
    }

    public function test_opt_in_demo_seed_is_labelled_and_does_not_reset_consumed_inventory(): void
    {
        $this->seed(HotelBookingDemoSeeder::class);
        $type = HotelRoomType::firstOrFail();
        $this->assertTrue($type->is_demo);
        $this->assertStringContainsString('DEMO', $type->hotel->name);
        $night = $type->inventory()->firstOrFail();
        $night->update(['available_rooms' => 1]);
        $this->seed(HotelBookingDemoSeeder::class);
        $this->assertSame(1, $night->fresh()->available_rooms);
        $this->assertDatabaseCount('hotels', 1);
        $this->assertDatabaseCount('hotel_room_inventory', 30);
    }
}
