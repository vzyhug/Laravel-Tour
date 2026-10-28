<?php

namespace Tests\Feature;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\TourDeparture;
use App\Services\Payments\PaymentWorkflow;
use App\Services\Payments\ReconcilePayments;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_attempt_can_retry_and_replayed_success_only_creates_one_invoice(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 1500000]);
        $service = app(PaymentWorkflow::class);
        $service->pay($booking, 'card', (string) Str::uuid(), 'failed');
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseCount('bill', 0);
        $key = (string) Str::uuid();
        $first = $service->pay($booking, 'card', $key, 'succeeded');
        $again = $service->pay($booking, 'card', $key, 'succeeded');
        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_pending_demo_resolution_requires_owner_and_late_success_is_refunded(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 1500000, 'hold_expires_at' => now()->addMinutes(5)]);
        $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->post(route('payments.demo.resolve', $attempt), ['outcome' => 'succeeded'])->assertForbidden();
        $booking->update(['hold_expires_at' => now()->subMinute()]);
        $this->withSession(['customer_id' => $booking->customer_id])->post(route('payments.demo.resolve', $attempt), ['outcome' => 'succeeded'])->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('refunded', $booking->payment()->firstOrFail()->status);
        $this->assertDatabaseHas('payment_refunds', ['amount' => 1500000, 'status' => 'succeeded']);
        $this->assertDatabaseCount('bill', 0);
        $this->post(route('payments.demo.resolve', $attempt), ['outcome' => 'failed'])->assertRedirect();
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_refunds_are_idempotent_and_cannot_exceed_collected_amount(): void
    {
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 1000000]);
        Payment::factory()->for($booking)->create(['amount' => 1000000]);
        $service = app(RefundService::class);
        $refund = $service->request($booking, 300000, 'first-refund', 'Hoàn một phần');
        $this->assertSame($refund->id, $service->request($booking, 300000, 'first-refund', 'Hoàn một phần')->id);
        $this->assertSame('completed', $booking->payment()->firstOrFail()->status);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->expectException(ValidationException::class);
        $service->request($booking, 800000, 'second-refund', 'Vượt số đã thu');
    }

    public function test_failed_refund_can_be_retried_without_marking_payment_refunded_early(): void
    {
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 1000000]);
        Payment::factory()->for($booking)->create(['amount' => 1000000]);
        config(['payments.mock_refund_outcome' => 'failed']);
        $service = app(RefundService::class);
        $refund = $service->request($booking, 1000000, 'retry-refund', 'Hủy chuyến');
        $this->assertSame('failed', $refund->status);
        $this->assertSame('completed', $booking->payment()->firstOrFail()->status);
        config(['payments.mock_refund_outcome' => 'succeeded']);
        $this->assertSame('succeeded', $service->process($refund)->status);
        $this->assertSame('refunded', $booking->payment()->firstOrFail()->status);
        $this->assertSame(2, $refund->fresh()->attempt_count);
    }

    public function test_reconciliation_detects_amount_tampering_instead_of_rewriting_payments(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 1000000]);
        $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'succeeded');
        $this->assertSame(0, app(ReconcilePayments::class)->handle());
        DB::table('mock_gateway_transactions')->where('reference', $attempt->provider_reference)->update(['amount' => 1]);
        $this->assertSame(1, app(ReconcilePayments::class)->handle());
        $this->assertDatabaseHas('payment_reconciliations', ['status' => 'mismatch']);
        $this->assertSame('1000000.00', $booking->payment()->firstOrFail()->amount);
    }

    public function test_admin_reconciliation_requires_role_and_renders_audit_results(): void
    {
        $this->post(route('admin.payments.reconcile'))->assertRedirect(route('login'));
        $this->withSession(['employee_id' => 1, 'employee_role' => 'Guide'])->post(route('admin.payments.reconcile'))->assertForbidden();
        $booking = Booking::factory()->create();
        app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'succeeded');
        $this->withSession(['employee_id' => 1, 'employee_role' => 'Admin'])->post(route('admin.payments.reconcile'))->assertRedirect();
        $this->get(route('admin.payments.index'))->assertOk()->assertSee('Đối soát mô phỏng')->assertSee('matched');
    }

    public function test_another_checkout_cannot_start_while_an_attempt_is_pending(): void
    {
        $booking = Booking::factory()->create();
        app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
        $this->withSession(['customer_id' => $booking->customer_id])->post(route('payments.store', $booking), ['payment_method' => 'card'])->assertStatus(409);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_stale_checkout_and_cached_attempt_use_the_current_departure(): void
    {
        $booking = Booking::factory()->create();
        $original = $booking->departure;
        $current = TourDeparture::factory()->create();
        DB::table('bookings')->where('booking_id', $booking->getKey())->update(['departure_id' => $current->getKey()]);
        $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
        $attempt->payment->setRelation('booking', $booking); // Deliberately cached old departure.
        $lockedDepartures = [];
        DB::listen(function ($query) use (&$lockedDepartures) {
            if (str_contains($query->sql, 'select') && str_contains($query->sql, '"tour_departures"')) {
                $lockedDepartures[] = (int) $query->bindings[0];
            }
        });
        app(PaymentWorkflow::class)->settle($attempt, 'succeeded');
        $this->assertNotEmpty($lockedDepartures);
        $this->assertSame($current->getKey(), $lockedDepartures[0]);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame($original->available_seats, $original->fresh()->available_seats);
    }

    public function test_a_departure_change_between_snapshot_and_booking_lock_rejects_checkout(): void
    {
        $booking = Booking::factory()->create();
        $target = TourDeparture::factory()->create();
        $changed = false;
        DB::listen(function ($query) use ($booking, $target, &$changed) {
            if (! $changed && str_contains($query->sql, 'select') && str_contains($query->sql, '"tour_departures"')) {
                $changed = true;
                DB::table('bookings')->where('booking_id', $booking->getKey())->update(['departure_id' => $target->getKey()]);
            }
        });
        try {
            app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'succeeded');
            $this->fail('Checkout must reject a departure changed during locking.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertTrue($changed);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_settlement_releases_expired_seats_once_even_if_refund_fails(): void
    {
        $departure = TourDeparture::factory()->create(['max_seats' => 10, 'available_seats' => 8]);
        $booking = Booking::factory()->for($departure, 'departure')->create(['total_adults' => 2, 'total_amount' => 1000000, 'hold_expires_at' => now()->addMinutes(5)]);
        $service = app(PaymentWorkflow::class);
        $attempt = $service->pay($booking, 'card', (string) Str::uuid(), 'pending');
        $booking->update(['hold_expires_at' => now()->subMinute()]);
        config(['payments.mock_refund_outcome' => 'failed']);
        $service->settle($attempt, 'succeeded'); // No expiry worker/controller called.
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(10, $departure->fresh()->available_seats);
        $this->assertSame('completed', $booking->payment()->firstOrFail()->status);
        $this->assertDatabaseHas('payment_refunds', ['status' => 'failed']);
        $this->assertDatabaseCount('bill', 0);
        $service->settle($attempt, 'succeeded');
        $this->assertFalse(app(ExpireBooking::class)->handle($booking));
        $this->assertSame(10, $departure->fresh()->available_seats);
        $this->assertDatabaseCount('payment_refunds', 1);
        config(['payments.mock_refund_outcome' => 'succeeded']);
        app(RefundService::class)->process($booking->payment->refunds()->firstOrFail());
        $this->assertSame('refunded', $booking->payment()->firstOrFail()->status);
        $this->assertSame(10, $departure->fresh()->available_seats);
    }

    public function test_pending_payment_screen_offers_explicit_demo_resolution(): void
    {
        $booking = Booking::factory()->create();
        app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
        $this->withSession(['customer_id' => $booking->customer_id])->get(route('payments.create', $booking))->assertOk()->assertSee('Mô phỏng thành công')->assertDontSee('Thanh toán đã hoàn tất.');
    }
}
