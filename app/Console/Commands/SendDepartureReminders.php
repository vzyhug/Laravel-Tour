<?php

namespace App\Console\Commands;

use App\Jobs\DeliverCustomerNotification;
use App\Models\Booking;
use App\Models\NotificationDelivery;
use App\Services\Notifications\TransactionalNotifier;
use Illuminate\Console\Command;

class SendDepartureReminders extends Command
{
    protected $signature = 'customer:departure-reminders';

    protected $description = 'Gửi nhắc khởi hành ngày mai và thử lại thông báo chưa gửi';

    public function handle(TransactionalNotifier $notifier): int
    {
        Booking::with('departure')->where('status', 'confirmed')->whereHas('departure', fn ($query) => $query
            ->where('status', '!=', 'cancelled')->whereDate('departure_date', today()->addDay()))
            ->each(fn ($booking) => $notifier->booking($booking, 'departure.reminder', 'departure.reminder:'.$booking->getKey().':'.$booking->departure->departure_date->toDateString()));
        NotificationDelivery::whereNull('sent_at')->where('created_at', '>=', now()->subDays(7))->each(fn ($delivery) => DeliverCustomerNotification::dispatch($delivery->id));

        return self::SUCCESS;
    }
}
