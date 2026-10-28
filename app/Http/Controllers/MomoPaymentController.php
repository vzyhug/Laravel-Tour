<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MomoPaymentSession;
use App\Services\Payments\MomoPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class MomoPaymentController extends Controller
{
    public function show(Booking $booking, MomoPaymentSession $session): View
    {
        $this->authorizeSession($booking, $session);
        $session->load('attempt.payment.booking.departure.tour');

        return view('booking.momo', compact('booking', 'session'));
    }

    public function qr(Booking $booking, MomoPaymentSession $session)
    {
        $this->authorizeSession($booking, $session);
        abort_unless($session->qr_payload, 404);

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(360, 8),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd,
        );
        $svg = (new \BaconQrCode\Writer($renderer))->writeString($session->qr_payload);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function status(Booking $booking, MomoPaymentSession $session): JsonResponse
    {
        $this->authorizeSession($booking, $session);
        app(MomoPaymentGateway::class)->refreshStatus($session);
        $session->refresh();

        return response()->json([
            'status' => $session->status,
            'result_code' => $session->result_code,
            'expired' => $session->expires_at->isPast(),
        ]);
    }

    public function returned(Booking $booking, MomoPaymentSession $session): RedirectResponse
    {
        $this->authorizeSession($booking, $session);

        return redirect()->route('payments.momo.show', [$booking, $session])
            ->with('success', 'MoMo đã chuyển kết quả về. Trang sẽ cập nhật khi IPN được xác nhận.');
    }

    public function ipn(Request $request, MomoPaymentGateway $gateway): JsonResponse
    {
        try {
            $gateway->handleIpn($request->all());
        } catch (Throwable $exception) {
            Log::warning('MoMo IPN rejected.', ['message' => $exception->getMessage()]);

            return response()->json(['resultCode' => 1, 'message' => 'Invalid notification'], 400);
        }

        return response()->json(['resultCode' => 0, 'message' => 'OK']);
    }

    private function authorizeSession(Booking $booking, MomoPaymentSession $session): void
    {
        $session->loadMissing('attempt.payment');
        abort_unless((int) $booking->getKey() === (int) $session->attempt->payment->booking_id, 404);
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
    }
}
