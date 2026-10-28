<?php

namespace App\Console\Commands;

use App\Jobs\DeliverCustomerNotification;
use App\Models\Booking;
use App\Models\NotificationDelivery;
use App\Services\Notifications\TransactionalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDepartureReminders extends Command
{
    protected $signature = 'customer:departure-reminders';

    protected $description = 'Gửi nhắc khởi hành ngày mai và thử lại thông báo chưa gửi';

    public function handle(TransactionalNotifier $notifier): int
    {
        Booking::with('departure')->where('status', 'confirmed')->whereHas('departure', fn ($query) => $query
            ->where('status', '!=', 'cancelled')->whereDate('departure_date', today()->addDay()))
            ->each(fn ($booking) => $notifier->booking($booking, 'departure.reminder', 'departure.reminder:'.$booking->getKey().':'.$booking->departure->departure_date->toDateString()));
        $failed = false;
        NotificationDelivery::whereNull('sent_at')->eachById(function ($delivery) use (&$failed) {
            try {
                DeliverCustomerNotification::dispatch($delivery->id);
            } catch (\Throwable $exception) {
                $failed = true;
                Log::warning('Customer notification retry could not be dispatched.', ['delivery_id' => $delivery->id, 'exception_type' => $exception::class]);
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
