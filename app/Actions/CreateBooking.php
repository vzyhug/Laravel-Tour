<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\TourDeparture;
use App\Services\TourQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function handle(int $customerId, array $data): Booking
    {
        app(ExpireBooking::class)->forDeparture((int) $data['departure_id']);

        return DB::transaction(function () use ($customerId, $data) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);
            $hashData = $data;
            unset($hashData['quote_token']);
            $requestHash = hash('sha256', json_encode($hashData, JSON_THROW_ON_ERROR));
            if (! empty($data['checkout_key'])) {
                $existing = Booking::where('customer_id', $customerId)->where('checkout_key', $data['checkout_key'])->first();
                if ($existing) {
                    if ($existing->request_hash !== $requestHash) {
                        throw ValidationException::withMessages(['checkout_key' => 'Yêu cầu này đã được dùng cho một đơn khác. Vui lòng bắt đầu đặt lại.']);
                    }

                    return $existing;
                }
            }
            $departure = TourDeparture::query()->lockForUpdate()->findOrFail($data['departure_id']);
            if (! $departure->tour->is_active || $departure->departure_date->isPast() && ! $departure->departure_date->isToday()) {
                throw ValidationException::withMessages(['departure_id' => 'Tour hoặc lịch khởi hành đã ngừng nhận đặt chỗ.']);
            }
            $quote = app(TourQuote::class);
            if (! empty($data['quote_token'])) {
                $quote->validate($data['quote_token'], $departure, $customerId);
            }
            $passengers = collect($data['passengers']);
            $adults = $passengers->where('passenger_type', 'adult')->count();
            $children = $passengers->where('passenger_type', 'child')->count();
            $seats = $adults + $children;

            if ($adults < 1) {
                throw ValidationException::withMessages(['passengers' => 'Đơn đặt tour phải có ít nhất một người lớn.']);
            }

            if ($departure->status !== 'open' || $departure->available_seats < $seats) {
                throw ValidationException::withMessages(['passengers' => 'Lịch khởi hành không còn đủ chỗ.']);
            }

            $snapshot = $quote->snapshot($departure);
            $subtotal = ($adults * $snapshot['adult_price']) + ($children * $snapshot['child_price']);

            // Mã giảm giá: mỗi đơn 1 mã, kiểm tra lại ở server và khóa dòng để không dùng trùng
            $coupon = null;
            $discount = 0;
            $code = Coupon::normalize((string) ($data['coupon_code'] ?? ''));

            if ($code !== '') {
                $coupon = Coupon::where('code', $code)->lockForUpdate()->first();
                $error = $coupon ? $coupon->check($customerId, $subtotal) : 'Mã giảm giá không tồn tại.';

                if ($error) {
                    throw ValidationException::withMessages(['coupon_code' => $error]);
                }

                $discount = $coupon->discountFor($subtotal);
            }

            $pickupType = $data['pickup_type'] ?? 'self';
            if (isset($data['expected_total']) && (int) $data['expected_total'] !== (int) ($subtotal - $discount)) {
                throw ValidationException::withMessages(['quote_token' => 'Tổng tiền vừa thay đổi. Vui lòng kiểm tra lại số khách và ưu đãi trước khi xác nhận.']);
            }
            $snapshot['contact'] = [
                'name' => $data['contact_name'] ?? $customer->full_name,
                'email' => $data['contact_email'] ?? $customer->email,
                'phone' => $data['contact_phone'] ?? $customer->phone,
            ];
            $snapshot['subtotal'] = $subtotal;
            $snapshot['discount'] = (int) $discount;
            $snapshot['coupon_code'] = $coupon?->code;
            $snapshot['total'] = (int) ($subtotal - $discount);

            $booking = Booking::create([
                'booking_code' => 'BK'.now()->format('ymd').Str::upper(Str::random(6)),
                'booking_date' => now(),
                'customer_id' => $customerId,
                'departure_id' => $departure->getKey(),
                'total_adults' => $adults,
                'total_children' => $children,
                'total_amount' => $subtotal - $discount,
                'coupon_id' => $coupon?->getKey(),
                'discount_amount' => $discount,
                'status' => 'pending',
                'checkout_key' => $data['checkout_key'] ?? null,
                'request_hash' => $requestHash,
                'service_snapshot' => $snapshot,
                'payment_expires_at' => now()->addMinutes(config('booking.payment_minutes')),
                'pickup_type' => $pickupType,
                'pickup_address' => $pickupType === 'door' ? ($data['pickup_address'] ?? null) : null,
                'pickup_note' => $data['pickup_note'] ?? null,
            ]);

            $booking->passengers()->createMany($passengers->all());
            $departure->available_seats -= $seats;
            if ($departure->available_seats === 0) {
                $departure->status = 'full';
            }
            $departure->save();
            $booking->recordEvent('held', ['seats' => $seats, 'total_vnd' => (int) $booking->total_amount, 'customer_id' => $customerId]);

            return $booking->load('departure.tour', 'passengers');
        });
    }
}
