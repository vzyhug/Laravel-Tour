<?php

namespace App\Actions;

use App\Models\Booking;
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

            $booking = Booking::create([
                'booking_code' => 'BK'.now()->format('ymd').Str::upper(Str::random(6)),
                'customer_id' => $customerId,
                'departure_id' => $departure->getKey(),
                'total_adults' => $adults,
                'total_children' => $children,
                'total_amount' => ($adults * $departure->adult_price) + ($children * $departure->child_price),
                'status' => 'pending',
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
