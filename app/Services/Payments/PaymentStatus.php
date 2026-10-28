<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\PaymentIntent;

class PaymentStatus
{
    public function state(Booking $booking, PaymentIntent $intent): array
    {
        $booking->loadMissing('payment', 'bill');
        $status = $booking->paymentIntents()->where('status', 'review')->exists() ? 'review' : $intent->status;
        if ($status === 'review') {
            // Keep an inconsistent provider receipt visible for manual reconciliation.
        } elseif ($booking->status === 'cancelled') {
            $status = 'cancelled';
        } elseif ($booking->payment?->status === 'completed') {
            $status = $intent->status === 'paid' ? 'paid' : 'superseded';
        } elseif (! in_array($booking->status, ['pending', 'confirmed'], true) || $intent->amount !== $booking->total_amount) {
            $status = 'cancelled';
        } elseif ($status === 'pending' && $intent->expires_at->lessThanOrEqualTo(now())) {
            $status = 'expired';
        }

        return ['status' => $status, 'reference' => $intent->reference, 'amount' => $intent->amount,
            'seconds_remaining' => $status === 'pending' ? max(0, $intent->expires_at->timestamp - now()->timestamp) : 0,
            'can_retry' => in_array($booking->status, ['pending', 'confirmed'], true) && (! $booking->payment || ($booking->payment->status === 'failed' && ! $booking->payment->attempts()->where('status', 'pending')->exists()))
                && in_array($status, ['expired', 'failed', 'cancelled'], true),
            'booking_url' => route('customer.bookings.show', $booking),
            'invoice_url' => $booking->bill && in_array($status, ['paid', 'superseded'], true) ? route('customer.bookings.invoice', $booking) : null];
    }
}
