<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Services\Payments\DemoQrPayment;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrPaymentController extends Controller
{
    private function authorizeIntent(Booking $booking, PaymentIntent $intent): void
    {
        abort_unless(DemoQrPayment::enabled(), 404);
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
        abort_unless($intent->booking_id === $booking->getKey() && $intent->provider === 'vietqr_demo', 404);
    }

    public function show(Booking $booking, PaymentIntent $intent, DemoQrPayment $payments): Response
    {
        $this->authorizeIntent($booking, $intent);

        return response()->view('booking.qr', ['booking' => $booking->load('departure.tour'), 'intent' => $intent,
            'state' => $payments->state($booking, $intent)])->header('Cache-Control', 'no-store, private');
    }

    public function status(Booking $booking, PaymentIntent $intent, DemoQrPayment $payments): JsonResponse
    {
        $this->authorizeIntent($booking, $intent);

        return response()->json($payments->state($booking, $intent))->header('Cache-Control', 'no-store, private');
    }

    public function image(Request $request, Booking $booking, PaymentIntent $intent, DemoQrPayment $payments): Response
    {
        $this->authorizeIntent($booking, $intent);
        abort_unless($payments->state($booking, $intent)['status'] === 'pending', 410, 'Mã QR đã đóng.');
        $writer = new Writer(new ImageRenderer(new RendererStyle(400, 4), new SvgImageBackEnd));
        $response = response($writer->writeString($intent->qr_payload))->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-store, private')->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; sandbox");
        if ($request->boolean('download')) {
            $response->header('Content-Disposition', 'attachment; filename="vietgo-demo-'.$intent->reference.'.svg"');
        }

        return $response;
    }

    public function demoBank(Booking $booking, PaymentIntent $intent, DemoQrPayment $payments): Response
    {
        $this->authorizeIntent($booking, $intent);

        return response()->view('booking.demo-bank', compact('booking', 'intent') + ['state' => $payments->state($booking, $intent)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function simulate(Request $request, Booking $booking, PaymentIntent $intent, DemoQrPayment $payments): RedirectResponse
    {
        $this->authorizeIntent($booking, $intent);
        $data = $request->validate(['outcome' => ['required', 'in:success,failed,expired'], 'amount' => ['prohibited'],
            'booking_id' => ['prohibited'], 'reference' => ['prohibited']]);
        $payments->simulate($intent, $data['outcome']);

        return redirect()->route('payments.qr.demo', [$booking, $intent])->with('success', 'Đã xử lý kết quả từ ngân hàng mô phỏng.');
    }
}
