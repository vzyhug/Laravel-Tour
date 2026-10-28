<?php

namespace App\Providers;

use App\Console\Commands\SendDepartureReminders;
use App\Models\Booking;
use App\Models\ItineraryConsultation;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\TourDeparture;
use App\Observers\CustomerTransactionObserver;
use App\Services\Notifications\TransactionalNotifier;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AccountRecoveryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([Booking::class, Payment::class, PaymentRefund::class, TourDeparture::class, ItineraryConsultation::class] as $model) {
            $model::observe(CustomerTransactionObserver::class);
        }
        Event::listen('App\\Events\\DepartureCancelled', function ($event) {
            Booking::whereIn('booking_id', $event->bookingIds)->each(function ($booking) {
                app(TransactionalNotifier::class)->booking($booking, 'booking.cancelled');
            });
        });
        if ($this->app->runningInConsole()) {
            $this->commands([SendDepartureReminders::class]);
        }
    }
}
