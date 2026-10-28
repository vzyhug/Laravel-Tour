<?php

namespace Tests\Feature;

use App\Jobs\DeliverCustomerNotification;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\TourDeparture;
use App\Notifications\CustomerTransaction;
use App\Services\Notifications\TransactionalNotifier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerTransactionNotificationTest extends TestCase
{
    use DatabaseMigrations;

    private function customer(): Customer
    {
        return Customer::create(['email' => 'notice@example.test', 'full_name' => 'Notice', 'phone' => '0901234567', 'password_hash' => Hash::make('Password123'), 'is_active' => true]);
    }

    public function test_notification_dispatches_only_after_commit_and_rollbacks_leave_no_delivery(): void
    {
        Queue::fake();
        $customer = $this->customer();
        DB::beginTransaction();
        app(TransactionalNotifier::class)->record($customer->getKey(), 'booking.created', 'rollback', []);
        Queue::assertNothingPushed();
        DB::rollBack();
        $this->assertDatabaseCount('notification_deliveries', 0);
        DB::beginTransaction();
        app(TransactionalNotifier::class)->record($customer->getKey(), 'booking.created', 'committed', []);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(DeliverCustomerNotification::class, 1);
    }

    public function test_duplicate_events_and_retried_jobs_send_only_once(): void
    {
        Queue::fake();
        Notification::fake();
        $customer = $this->customer();
        $service = app(TransactionalNotifier::class);
        $service->record($customer->getKey(), 'booking.created', 'same-event', []);
        $service->record($customer->getKey(), 'booking.created', 'same-event', []);
        $this->assertDatabaseCount('notification_deliveries', 1);
        $job = new DeliverCustomerNotification(NotificationDelivery::firstOrFail()->id);
        $job->handle();
        $job->handle();
        Notification::assertSentToTimes($customer, CustomerTransaction::class, 1);
    }

    public function test_reminders_are_deduplicated_and_cancelled_bookings_are_skipped(): void
    {
        Queue::fake();
        Notification::fake();
        $departure = TourDeparture::factory()->create(['departure_date' => today()->addDay()]);
        $booking = Booking::factory()->confirmed()->create(['departure_id' => $departure->getKey()]);
        $this->artisan('customer:departure-reminders')->assertSuccessful();
        $this->artisan('customer:departure-reminders')->assertSuccessful();
        $this->assertSame(1, NotificationDelivery::where('event', 'departure.reminder')->count());
        $delivery = NotificationDelivery::where('event', 'departure.reminder')->firstOrFail();
        $booking->update(['status' => 'cancelled']);
        (new DeliverCustomerNotification($delivery->id))->handle();
        Notification::assertNothingSent();
    }

    public function test_delivery_transport_failure_does_not_fail_committed_business_transaction(): void
    {
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('Queue unavailable'));
        $customer = $this->customer();
        DB::transaction(fn () => app(TransactionalNotifier::class)->record($customer->getKey(), 'booking.created', 'queue-outage', []));
        $this->assertDatabaseHas('notification_deliveries', ['event' => 'booking.created', 'sent_at' => null]);
    }
}
