<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\TourDeparture;
use App\Services\Operations\SaveDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingOperationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_started_or_completed_departures_cannot_accept_new_bookings_even_if_sales_status_is_open(): void
    {
        $this->withoutVite();
        $customer = Customer::factory()->create();
        $this->withSession(['customer_id' => $customer->getKey()]);
        foreach (['in_progress', 'completed'] as $status) {
            $departure = TourDeparture::factory()->create(['departure_date' => today(), 'status' => 'open', 'max_seats' => 10, 'available_seats' => 10]);
            $departure->forceFill(['operation_status' => $status])->save();
            $this->get(route('bookings.create', $departure))->assertNotFound();
            $this->post(route('bookings.store'), [
                'departure_id' => $departure->getKey(), 'pickup_type' => 'self',
                'passengers' => [['full_name' => 'Khách kiểm thử', 'id_card' => '012345678901']],
            ])->assertSessionHasErrors('passengers');
            $this->assertSame(10, $departure->fresh()->available_seats);
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_cannot_complete_booking_while_tour_is_still_underway(): void
    {
        $admin = Employee::factory()->admin()->create();
        $departure = TourDeparture::factory()->create(['departure_date' => today()->subDay(), 'return_date' => today()->addDay()]);
        $booking = Booking::factory()->confirmed()->for($departure, 'departure')->create();
        $this->withSession(['employee_id' => $admin->getKey(), 'employee_role' => 'Admin']);
        $this->patch(route('admin.bookings.status', $booking), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $departure->update(['return_date' => today()]);
        $this->patch(route('admin.bookings.status', $booking), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_starting_departure_closes_sales_and_prevents_schedule_edits(): void
    {
        $this->withoutVite();
        $guide = Employee::factory()->guide()->create();
        $departure = TourDeparture::factory()->create([
            'guide_id' => $guide->getKey(), 'departure_date' => today(), 'return_date' => today()->addDay(),
            'max_seats' => 10, 'available_seats' => 10, 'status' => 'open',
        ]);
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide']);
        $this->patch(route('operations.status', $departure), ['operation_status' => 'in_progress'])->assertRedirect();
        $this->assertSame('full', $departure->fresh()->status);
        $this->assertSame('in_progress', $departure->fresh()->operation_status);
        $data = $departure->only(['tour_id', 'departure_date', 'return_date', 'max_seats', 'available_seats', 'adult_price', 'guide_id', 'status']);
        try {
            app(SaveDeparture::class)->handle($data, $departure);
            $this->fail('An active departure must reject schedule edits.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('in_progress', $departure->fresh()->operation_status);
    }

    public function test_editing_empty_departure_recomputes_inventory_from_capacity(): void
    {
        $departure = TourDeparture::factory()->create(['max_seats' => 10, 'available_seats' => 10]);
        $data = $departure->only(['tour_id', 'departure_date', 'return_date', 'adult_price', 'guide_id']);
        app(SaveDeparture::class)->handle([
            ...$data, 'max_seats' => 12, 'available_seats' => 1, 'status' => 'open',
        ], $departure);
        $this->assertSame(12, $departure->fresh()->available_seats);
    }
}
