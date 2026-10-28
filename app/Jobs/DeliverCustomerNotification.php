<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Notifications\CustomerTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeliverCustomerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $delivery = NotificationDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || $delivery->sent_at) {
                return;
            }
            $customer = Customer::find($delivery->customer_id);
            if (! $customer || ! $customer->is_active) {
                return;
            }
            if ($delivery->event === 'departure.reminder') {
                $booking = Booking::with('departure')->find($delivery->payload['booking_id']);
                if (! $booking || $booking->status !== 'confirmed' || $booking->departure->status === 'cancelled' || ! $booking->departure->departure_date->isTomorrow()) {
                    // Consume a reminder that has become stale; never send it after cancellation/date change.
                    $delivery->update(['sent_at' => now()]);

                    return;
                }
            }
            $customer->notify(new CustomerTransaction($delivery->event, $delivery->payload));
            $delivery->update(['sent_at' => now()]);
        });
    }
}
