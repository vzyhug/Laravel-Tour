<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeBookingDeparture
{
    public function handle(Booking $booking, int $customerId, int $targetId, int $expectedDepartureId, string $reason, ?array $expectedPrices = null): Booking
    {
        return DB::transaction(function () use ($booking, $customerId, $targetId, $expectedDepartureId, $reason, $expectedPrices) {
            $snapshot = Booking::query()->findOrFail($booking->getKey());
            $departures = TourDeparture::query()->with('tour')->whereIn('departure_id', [$snapshot->departure_id, $targetId])
                ->orderBy('departure_id')->lockForUpdate()->get()->keyBy('departure_id');
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            abort_unless((int) $booking->customer_id === $customerId, 403);
            $reject = fn (string $message) => throw ValidationException::withMessages(['departure_id' => $message]);
            if ($booking->status !== 'pending' || $booking->holdHasExpired() || $booking->payment()->exists() || $booking->consultation_id !== null) {
                $reject('Chỉ đổi đơn tour thông thường còn hạn giữ chỗ, chưa tạo giao dịch thanh toán.');
            }
            if ((int) $booking->departure_id !== (int) $snapshot->departure_id || (int) $booking->departure_id !== $expectedDepartureId || $targetId === (int) $booking->departure_id) {
                $reject('Đơn đã thay đổi hoặc bạn đang chọn lịch hiện tại. Vui lòng tải lại trang.');
            }
            $source = $departures->get($booking->departure_id);
            $target = $departures->get($targetId);
            $seats = $booking->total_adults + $booking->total_children;
            if (! $target || $source->operation_status !== 'scheduled' || $target->operation_status !== 'scheduled' || $source->is_private || $source->departure_date->lte(today()) || $source->status === 'cancelled'
                || $target->is_private || $target->status !== 'open' || $target->departure_date->lte(today())
                || ! $target->tour->is_active || $target->available_seats < $seats) {
                $reject('Lịch khởi hành không hợp lệ, đã khởi hành hoặc không đủ chỗ.');
            }
            // Compare the displayed fares while the target is locked. Never silently
            // charge a new price after the customer has accepted an older quote.
            if ($expectedPrices !== null && (
                number_format((float) $expectedPrices['adult_price'], 2, '.', '') !== $target->adult_price
                || ($booking->total_children > 0 && number_format((float) $expectedPrices['child_price'], 2, '.', '') !== $target->child_price)
            )) {
                $reject('Giá lịch khởi hành đã thay đổi. Vui lòng kiểm tra giá mới và xác nhận lại.');
            }
            $subtotal = $booking->total_adults * (float) $target->adult_price + $booking->total_children * (float) $target->child_price;
            $discount = 0;
            if ($booking->coupon_id !== null) {
                $coupon = Coupon::query()->lockForUpdate()->findOrFail($booking->coupon_id);
                if (! $coupon->is_active || ($coupon->starts_on && $coupon->starts_on->isFuture())
                    || ($coupon->expires_on && $coupon->expires_on->lt(today())) || $subtotal < (float) $coupon->min_order) {
                    $reject('Mã giảm giá của đơn không còn hợp lệ với lịch mới. Vui lòng liên hệ hỗ trợ.');
                }
                $discount = $coupon->discountFor($subtotal);
            }
            $newAmount = $subtotal - $discount;
            $booking->changes()->create([
                'from_departure_id' => $source->getKey(), 'to_departure_id' => $target->getKey(),
                'previous_amount' => $booking->total_amount, 'new_amount' => $newAmount, 'reason' => $reason,
            ]);
            $source->available_seats = min($source->max_seats, $source->available_seats + $seats);
            if ($source->status === 'full') {
                $source->status = 'open';
            }
            $source->save();
            $target->available_seats -= $seats;
            if ($target->available_seats === 0) {
                $target->status = 'full';
            }
            $target->save();
            // Keep the original deadline: changing a departure must not extend the hold.
            $booking->update([
                'departure_id' => $target->getKey(), 'total_amount' => $newAmount, 'discount_amount' => $discount,
                'pickup_type' => 'self', 'pickup_address' => null, 'pickup_note' => null,
            ]);

            return $booking;
        }, 3);
    }
}
