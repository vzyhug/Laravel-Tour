<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function handle(int $customerId, array $data): Booking
    {
        return DB::transaction(function () use ($customerId, $data) {
            $departure = TourDeparture::query()->lockForUpdate()->findOrFail($data['departure_id']);
            $passengers = collect($data['passengers'])->map(fn (array $passenger) => [
                ...$passenger,
                'passenger_type' => 'adult', // Retained for legacy booking records; new bookings have one passenger category.
            ]);
            $adults = $passengers->count();
            $children = 0;
            $seats = $adults;

            if ($departure->status !== 'open' || $departure->available_seats < $seats) {
                throw ValidationException::withMessages(['passengers' => 'Lịch khởi hành không còn đủ chỗ.']);
            }

            $subtotal = $adults * (float) $departure->adult_price;

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

            $booking = Booking::create([
                'booking_code' => 'BK'.now()->format('ymd').Str::upper(Str::random(6)),
                'customer_id' => $customerId,
                'departure_id' => $departure->getKey(),
                'total_adults' => $adults,
                'total_children' => $children,
                'total_amount' => $subtotal - $discount,
                'coupon_id' => $coupon?->getKey(),
                'discount_amount' => $discount,
                'status' => 'pending',
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

            return $booking->load('departure.tour', 'passengers');
        });
    }
}
