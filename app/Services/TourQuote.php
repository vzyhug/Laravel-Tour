<?php

namespace App\Services;

use App\Models\TourDeparture;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class TourQuote
{
    public function snapshot(TourDeparture $departure): array
    {
        $tour = $departure->tour;

        return [
            'title' => $tour->title,
            'departure_date' => $departure->departure_date->format('Y-m-d'),
            'return_date' => $departure->return_date->format('Y-m-d'),
            'departure_time' => $departure->departure_time,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'pickup_point' => $tour->pickup_point,
            'duration_days' => $tour->duration_days,
            'duration_nights' => $tour->duration_nights,
            'adult_price' => (int) round((float) $departure->adult_price),
            'child_price' => (int) round((float) $departure->child_price),
            'included_services' => $tour->included_services,
            'policy' => [
                'cancel_before_days' => (int) config('booking.cancel_before_days'),
                'full_refund_hours' => (int) config('booking.full_refund_hours'),
                'partial_refund_percent' => (int) config('booking.partial_refund_percent'),
            ],
            'demo' => true,
        ];
    }

    public function issue(TourDeparture $departure, int $customerId): string
    {
        return Crypt::encryptString(json_encode([
            'customer_id' => $customerId,
            'departure_id' => $departure->getKey(),
            'expires_at' => now()->addMinutes(config('booking.quote_minutes'))->timestamp,
            'snapshot' => $this->snapshot($departure),
        ], JSON_THROW_ON_ERROR));
    }

    public function validate(string $token, TourDeparture $departure, int $customerId): void
    {
        try {
            $quote = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['quote_token' => 'Báo giá không hợp lệ. Vui lòng tải lại trang để kiểm tra giá.']);
        }

        if (($quote['customer_id'] ?? null) !== $customerId || ($quote['departure_id'] ?? null) !== $departure->getKey()
            || ($quote['expires_at'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['quote_token' => 'Báo giá đã hết hạn hoặc không thuộc lựa chọn này. Vui lòng kiểm tra và xác nhận lại.']);
        }
        if (($quote['snapshot'] ?? null) !== $this->snapshot($departure)) {
            throw ValidationException::withMessages(['quote_token' => 'Giá hoặc điều kiện tour vừa thay đổi. Vui lòng kiểm tra thông tin mới trước khi tiếp tục.']);
        }
    }
}
