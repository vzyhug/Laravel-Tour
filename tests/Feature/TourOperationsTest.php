<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\DepartureReport;
use App\Models\Employee;
use App\Models\PassengerAttendance;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Services\Operations\CancelDeparture;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guide_only_sees_assigned_departures_and_cannot_access_another_manifest(): void
    {
        $guide = Employee::factory()->guide()->create();
        $own = TourDeparture::factory()->create(['guide_id' => $guide->getKey()]);
        $other = TourDeparture::factory()->create();
        $this->asEmployee($guide);
        $this->get(route('operations.index'))->assertOk()->assertSee($own->tour->title)->assertDontSee($other->tour->title);
        $this->get(route('operations.show', $other))->assertForbidden();
        $guide->update(['is_active' => false]);
        $this->get(route('operations.show', $own))->assertForbidden();
    }

    public function test_attendance_completion_and_report_have_a_guarded_lifecycle(): void
    {
        $guide = Employee::factory()->guide()->create();
        $departure = TourDeparture::factory()->create(['guide_id' => $guide->getKey(), 'departure_date' => today(), 'return_date' => today()]);
        $booking = Booking::factory()->confirmed()->create(['departure_id' => $departure->getKey(), 'total_adults' => 1]);
        $passenger = BookingPassenger::factory()->create(['booking_id' => $booking->getKey()]);
        $foreignPassenger = BookingPassenger::factory()->create();
        $this->asEmployee($guide);
        $this->put(route('operations.attendance', [$departure, $foreignPassenger]), ['status' => 'present'])->assertUnprocessable();
        $this->put(route('operations.report', $departure), ['summary' => 'Kết thúc tốt'])->assertUnprocessable();
        $this->patch(route('operations.status', $departure), ['operation_status' => 'in_progress'])->assertRedirect();
        $this->patch(route('operations.status', $departure), ['operation_status' => 'completed'])->assertUnprocessable();
        $this->put(route('operations.attendance', [$departure, $passenger]), ['status' => 'present'])->assertRedirect();
        $this->put(route('operations.attendance', [$departure, $passenger]), ['status' => 'absent', 'notes' => 'Không đến'])->assertRedirect();
        $this->assertSame(1, PassengerAttendance::count());
        $this->patch(route('operations.status', $departure), ['operation_status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->put(route('operations.attendance', [$departure, $passenger]), ['status' => 'present'])->assertStatus(409);
        $this->put(route('operations.report', $departure), ['summary' => 'Đã hoàn thành', 'incidents' => 'Một khách vắng'])->assertRedirect();
        $this->assertSame(1, DepartureReport::count());
        $this->get(route('operations.show', $departure))->assertOk()->assertSee('Đã hoàn thành');
    }

    public function test_future_departure_cannot_be_started_or_have_attendance_recorded(): void
    {
        $guide = Employee::factory()->guide()->create();
        $departure = TourDeparture::factory()->create(['guide_id' => $guide->getKey()]);
        $booking = Booking::factory()->confirmed()->create(['departure_id' => $departure->getKey()]);
        $passenger = BookingPassenger::factory()->create(['booking_id' => $booking->getKey()]);
        $this->asEmployee($guide);
        $this->patch(route('operations.status', $departure), ['operation_status' => 'in_progress'])->assertUnprocessable();
        $this->put(route('operations.attendance', [$departure, $passenger]), ['status' => 'present'])->assertUnprocessable();
    }

    public function test_cancelling_departure_cancels_active_bookings_and_refunds_once(): void
    {
        $admin = Employee::factory()->admin()->create();
        $departure = TourDeparture::factory()->create(['max_seats' => 10, 'available_seats' => 7]);
        $paid = Booking::factory()->confirmed()->create(['departure_id' => $departure->getKey(), 'total_adults' => 2, 'total_amount' => 2000000]);
        $pending = Booking::factory()->create(['departure_id' => $departure->getKey(), 'total_adults' => 1]);
        $payment = Payment::factory()->create(['booking_id' => $paid->getKey(), 'amount' => 2000000]);
        $this->asEmployee($admin);
        $this->delete(route('admin.departures.destroy', $departure), ['cancellation_reason' => 'Thời tiết xấu'])->assertRedirect();
        $this->assertSame('cancelled', $paid->fresh()->status);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(10, $departure->fresh()->available_seats);
        $this->assertDatabaseHas('payment_refunds', ['payment_id' => $payment->getKey(), 'amount' => 2000000, 'status' => 'succeeded']);
        $this->delete(route('admin.departures.destroy', $departure))->assertRedirect();
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertSame(10, $departure->fresh()->available_seats);
    }

    public function test_guide_assignment_rejects_inactive_wrong_role_and_overlapping_guide(): void
    {
        $admin = Employee::factory()->admin()->create();
        $guide = Employee::factory()->guide()->create();
        $inactive = Employee::factory()->guide()->create(['is_active' => false]);
        $tour = Tour::factory()->create(['duration_days' => 2]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'guide_id' => $guide->getKey(), 'departure_date' => today()->addDays(10), 'return_date' => today()->addDays(11)]);
        $this->asEmployee($admin);
        $payload = [
            'tour_id' => $tour->getKey(), 'departure_date' => today()->addDays(11)->toDateString(),
            'return_date' => today()->addDays(12)->toDateString(), 'max_seats' => 10,
            'available_seats' => 10, 'adult_price' => 1000000, 'status' => 'open',
        ];
        foreach ([$admin, $inactive, $guide] as $invalid) {
            $this->post(route('admin.departures.store'), [...$payload, 'guide_id' => $invalid->getKey()])->assertSessionHasErrors('guide_id');
        }
        $this->post(route('admin.departures.store'), [...$payload, 'guide_id' => $guide->getKey(), 'departure_date' => today()->addDays(12)->toDateString(), 'return_date' => today()->addDays(13)->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, TourDeparture::count());
    }

    public function test_form_cancellation_uses_same_service_and_cannot_reopen_departure(): void
    {
        $admin = Employee::factory()->admin()->create();
        $tour = Tour::factory()->create(['duration_days' => 1]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'departure_date' => today()->addDays(10), 'return_date' => today()->addDays(10)]);
        $booking = Booking::factory()->create(['departure_id' => $departure->getKey()]);
        $this->asEmployee($admin);
        $payload = [
            'tour_id' => $tour->getKey(), 'departure_date' => $departure->departure_date->toDateString(),
            'return_date' => $departure->return_date->toDateString(), 'max_seats' => $departure->max_seats,
            'available_seats' => $departure->available_seats, 'adult_price' => 1000000, 'status' => 'cancelled',
        ];
        $this->put(route('admin.departures.update', $departure), $payload)->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->put(route('admin.departures.update', $departure), [...$payload, 'status' => 'open'])->assertSessionHasErrors('status');
    }

    public function test_failed_refund_rolls_back_departure_and_bookings(): void
    {
        $admin = Employee::factory()->admin()->create();
        $departure = TourDeparture::factory()->create(['max_seats' => 10, 'available_seats' => 8]);
        $booking = Booking::factory()->confirmed()->create(['departure_id' => $departure->getKey(), 'total_adults' => 2]);
        $payment = Payment::factory()->create(['booking_id' => $booking->getKey()]);
        $this->mock(RefundService::class, fn ($mock) => $mock->shouldReceive('request')->once()->andThrow(new \RuntimeException('Gateway unavailable')));
        try {
            app(CancelDeparture::class)->handle($departure, $admin->getKey(), 'Hủy chuyến');
            $this->fail('Expected gateway failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Gateway unavailable', $exception->getMessage());
        }
        $this->assertSame('open', $departure->fresh()->status);
        $this->assertSame(8, $departure->fresh()->available_seats);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_refunds', 0);
    }

    public function test_editing_capacity_preserves_reserved_seats_and_rejects_silent_date_change(): void
    {
        $admin = Employee::factory()->admin()->create();
        $tour = Tour::factory()->create(['duration_days' => 1]);
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'departure_date' => today()->addDays(10), 'return_date' => today()->addDays(10), 'max_seats' => 10, 'available_seats' => 8]);
        Booking::factory()->create(['departure_id' => $departure->getKey(), 'total_adults' => 2]);
        $this->asEmployee($admin);
        $payload = [
            'tour_id' => $tour->getKey(), 'departure_date' => $departure->departure_date->toDateString(),
            'return_date' => $departure->return_date->toDateString(), 'max_seats' => 11,
            'available_seats' => 11, 'adult_price' => 1000000, 'status' => 'open',
        ];
        $this->put(route('admin.departures.update', $departure), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(9, $departure->fresh()->available_seats);
        $this->put(route('admin.departures.update', $departure), [...$payload, 'departure_date' => today()->addDays(11)->toDateString(), 'return_date' => today()->addDays(11)->toDateString()])->assertSessionHasErrors('departure_date');
        $this->assertSame(today()->addDays(10)->toDateString(), $departure->fresh()->departure_date->toDateString());
    }

    private function asEmployee(Employee $employee): void
    {
        $this->withSession(['employee_id' => $employee->getKey(), 'employee_role' => $employee->role->role_name]);
    }
}
