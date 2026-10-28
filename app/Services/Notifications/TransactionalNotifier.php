<?php

namespace App\Services\Notifications;

use App\Jobs\DeliverCustomerNotification;
use App\Models\Booking;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionalNotifier
{
    public function record(int $customerId, string $event, string $key, array $payload): void
    {
        // insertOrIgnore uses the database UNIQUE key to deduplicate competing producers.
        DB::table('notification_deliveries')->insertOrIgnore([
            'customer_id' => $customerId,
            'event' => $event,
            'deduplication_key' => hash('sha256', $key),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $delivery = NotificationDelivery::where('deduplication_key', hash('sha256', $key))->first();
        if ($delivery && ! $delivery->sent_at) {
            $id = $delivery->id;
            DB::afterCommit(function () use ($id) {
                try {
                    DeliverCustomerNotification::dispatch($id);
                } catch (\Throwable $exception) {
                    // Checkout has already committed. Preserve the outbox for the scheduled retry;
                    // a queue/mail outage must not turn a successful purchase into an HTTP failure.
                    Log::warning('Customer notification deferred for retry.', ['delivery_id' => $id, 'exception_type' => $exception::class]);
                }
            });
        }
    }

    public function booking(Booking $booking, string $event, ?string $key = null): void
    {
        $this->record($booking->customer_id, $event, $key ?? $event.':'.$booking->getKey(), [
            'booking_id' => $booking->getKey(),
            'booking_code' => $booking->booking_code,
        ]);
    }
}
