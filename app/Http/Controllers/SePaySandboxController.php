<?php

namespace App\Http\Controllers;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Services\Payments\PaymentStatus;
use App\Services\Payments\SePaySandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SePaySandboxController extends Controller
{
    private function authorizeIntent(Booking $booking, PaymentIntent $intent): void
    {
        abort_unless(SePaySandbox::enabled(), 404);
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
        abort_unless($intent->booking_id === $booking->getKey() && $intent->provider === SePaySandbox::PROVIDER, 404);
    }

    public function checkout(Booking $booking, PaymentIntent $intent, SePaySandbox $gateway, PaymentStatus $statuses): Response|RedirectResponse
    {
        $this->authorizeIntent($booking, $intent);
        app(ExpireBooking::class)->handle($booking);
        if ($statuses->state($booking->fresh(), $intent)['status'] !== 'pending') {
            return redirect()->route('payments.sepay.show', [$booking, $intent]);
        }

        return response()->view('booking.sepay-checkout', ['booking' => $booking, 'intent' => $intent, 'fields' => $gateway->fields($intent)])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(Booking $booking, PaymentIntent $intent, SePaySandbox $gateway, PaymentStatus $statuses): Response
    {
        $this->authorizeIntent($booking, $intent);
        $available = $gateway->synchronize($intent);
        $state = $statuses->state($booking->fresh(), $intent->fresh());

        return response()->view('booking.sepay-sandbox', ['booking' => $booking->load('departure.tour'), 'intent' => $intent,
            'state' => $state, 'available' => $available])->header('Cache-Control', 'no-store, private');
    }

    public function status(Booking $booking, PaymentIntent $intent, SePaySandbox $gateway, PaymentStatus $statuses): JsonResponse
    {
        $this->authorizeIntent($booking, $intent);
        $available = $gateway->synchronize($intent);

        return response()->json($statuses->state($booking->fresh(), $intent->fresh()) + ['verification_available' => $available])
            ->header('Cache-Control', 'no-store, private');
    }
}
