<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\ItineraryConsultation;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\TourDeparture;
use App\Services\Notifications\TransactionalNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerTransactionObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof Booking) {
            app(TransactionalNotifier::class)->booking($model, 'booking.created');
        } elseif ($model instanceof Payment && $model->status === 'completed') {
            $this->payment($model);
        }
    }

    public function updated(Model $model): void
    {
        $notifier = app(TransactionalNotifier::class);
        if ($model instanceof Booking && $model->wasChanged('status') && in_array($model->status, ['cancelled', 'expired'], true)) {
            $notifier->booking($model, 'booking.'.$model->status);
        } elseif ($model instanceof Payment && $model->wasChanged('status') && in_array($model->status, ['completed', 'failed', 'refunded'], true)) {
            $this->payment($model);
        } elseif ($model instanceof PaymentRefund && $model->wasChanged('status') && $model->status === 'succeeded') {
            $payment = $model->payment()->first();
            $booking = $payment?->booking()->first();
            if ($booking) {
                $notifier->booking($booking, 'refund.succeeded', 'refund.succeeded:'.$model->id);
            }
        } elseif ($model instanceof TourDeparture && $model->status !== 'cancelled' && $model->wasChanged(['departure_date', 'departure_time'])) {
            $version = (string) Str::uuid();
            Booking::where('departure_id', $model->getKey())->whereIn('status', ['pending', 'confirmed'])->each(function ($booking) use ($notifier, $version) {
                $notifier->booking($booking, 'departure.changed', 'departure.changed:'.$booking->getKey().':'.$version);
            });
        } elseif ($model instanceof ItineraryConsultation && $model->status === 'quoted' && $model->wasChanged(['status', 'quoted_total', 'reply', 'valid_until'])) {
            $plan = $model->itinerary()->first();
            if ($plan) {
                $notifier->record($plan->customer_id, 'consultation.quoted', 'consultation.quoted:'.$model->id.':'.$model->version, ['consultation_id' => $model->id]);
            }
        }
    }

    private function payment(Payment $payment): void
    {
        $booking = $payment->booking()->first();
        if ($payment->status === 'refunded' && $payment->refunds()->where('status', 'succeeded')->exists()) {
            return; // Individual refund success already generates its own delivery.
        }
        if ($booking) {
            app(TransactionalNotifier::class)->booking($booking, 'payment.'.$payment->status, 'payment.'.$payment->status.':'.$payment->getKey());
        }
    }
}
