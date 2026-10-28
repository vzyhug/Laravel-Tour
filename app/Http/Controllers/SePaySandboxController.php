<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Services\Payments\PaymentStatus;
use App\Services\Payments\SePaySandbox;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class SePaySandboxController extends Controller
{
    private function authorizeIntent(Booking $booking, PaymentIntent $intent): void
    {
        abort_unless(SePaySandbox::enabled(), 404);
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
        abort_unless($intent->booking_id === $booking->getKey() && $intent->provider === SePaySandbox::PROVIDER, 404);
    }

    public function show(Booking $booking, PaymentIntent $intent, SePaySandbox $gateway, PaymentStatus $statuses): Response
    {
        $this->authorizeIntent($booking, $intent);
        $state = $statuses->state($booking, $intent);
        $fields = $state['status'] === 'pending' && SePaySandbox::ready() ? $gateway->fields($intent) : [];

        return response()->view('booking.sepay-sandbox', ['booking' => $booking->load('departure.tour'), 'intent' => $intent,
            'state' => $state, 'fields' => $fields])->header('Cache-Control', 'no-store, private');
    }

    public function status(Booking $booking, PaymentIntent $intent, PaymentStatus $statuses): JsonResponse
    {
        $this->authorizeIntent($booking, $intent);

        return response()->json($statuses->state($booking, $intent))->header('Cache-Control', 'no-store, private');
    }

    public function phoneImage(Booking $booking, PaymentIntent $intent, PaymentStatus $statuses): Response
    {
        $this->authorizeIntent($booking, $intent);
        abort_unless(SePaySandbox::ready() && $statuses->state($booking, $intent)['status'] === 'pending', 410);
        $path = URL::temporarySignedRoute('payments.sepay.phone', $intent->expires_at, ['intent' => $intent], absolute: false);
        $link = rtrim(config('payments.sepay_sandbox.public_url'), '/').$path;
        $writer = new Writer(new ImageRenderer(new RendererStyle(400, 4), new SvgImageBackEnd));

        return response($writer->writeString($link))->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff')->header('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function phone(PaymentIntent $intent, SePaySandbox $gateway, PaymentStatus $statuses): Response
    {
        abort_unless(SePaySandbox::ready() && $intent->provider === SePaySandbox::PROVIDER, 404);
        abort_unless($statuses->state($intent->booking, $intent)['status'] === 'pending', 410);

        return response()->view('booking.sepay-phone', ['intent' => $intent, 'fields' => $gateway->fields($intent)])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; form-action https://pay-sandbox.sepay.vn; frame-ancestors 'none'");
    }

    public function ipn(Request $request, SePaySandbox $gateway): JsonResponse
    {
        abort_unless(SePaySandbox::enabled(), 404);
        $secret = (string) config('payments.sepay_sandbox.ipn_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->header('X-Secret-Key')), 401);
        abort_unless($request->isJson(), 415);
        abort_if(strlen($request->getContent()) > 32768, 413);
        $data = $request->validate([
            'notification_type' => ['required', 'in:ORDER_PAID,TRANSACTION_VOID'],
            'order' => ['required', 'array'], 'order.order_invoice_number' => ['required', 'string', 'max:25'],
            'order.order_id' => ['required', 'string', 'max:100'], 'order.order_status' => ['required', 'string', 'max:30'],
            'order.order_currency' => ['required', 'string', 'max:3'], 'order.order_amount' => ['required', 'regex:/^[0-9]{1,13}(?:\.[0-9]{1,2})?$/D'],
            'transaction' => ['required', 'array'], 'transaction.transaction_id' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'transaction.payment_method' => ['required', 'string', 'max:30'], 'transaction.transaction_type' => ['required', 'string', 'max:30'],
            'transaction.transaction_status' => ['required', 'string', 'max:30'], 'transaction.transaction_currency' => ['required', 'string', 'max:3'],
            'transaction.transaction_amount' => ['required', 'regex:/^[0-9]{1,13}(?:\.[0-9]{1,2})?$/D'],
        ]);
        $gateway->receive($data);

        return response()->json(['success' => true])->header('Cache-Control', 'no-store');
    }

    public function returned(): Response
    {
        abort_unless(SePaySandbox::enabled(), 404);

        // A browser redirect is not proof of payment; only an authenticated IPN can confirm an order.
        return response()->view('booking.sepay-return')->header('Cache-Control', 'no-store');
    }
}
