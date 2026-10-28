<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelPayment;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HotelBookingService
{
    public function dates(string $checkIn, string $checkOut): array
    {
        Validator::make(['check_in' => $checkIn, 'check_out' => $checkOut], [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
        ])->validate();
        $start = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();
        if ($start->lt(today()) || $end->lte($start) || $start->diffInDays($end) > 30) {
            throw ValidationException::withMessages(['check_out' => 'Chọn từ 1 đến 30 đêm, ngày nhận phòng không được trong quá khứ.']);
        }
        $dates = [];
        for ($day = $start; $day->lt($end); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public function hold(int $customerId, array $data): HotelBooking
    {
        $data = Validator::make($data, [
            'request_key' => ['required', 'uuid'],
            'room_type_id' => ['required', 'integer', 'exists:hotel_room_types,id'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
            'rooms' => ['required', 'integer', 'between:1,10'],
            'guests' => ['required', 'integer', 'between:1,100'],
        ])->validate();
        $dates = $this->dates($data['check_in'], $data['check_out']);

        return DB::transaction(function () use ($customerId, $data, $dates) {
            // Serialize holds and inventory edits on the room type, then lock nights in date order.
            $type = HotelRoomType::query()->lockForUpdate()->findOrFail($data['room_type_id']);
            $existing = HotelBooking::where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->customer_id != $customerId || $existing->room_type_id != $type->id || $existing->check_in->toDateString() !== $data['check_in'] || $existing->check_out->toDateString() !== $data['check_out'] || $existing->rooms != $data['rooms'] || $existing->guests != $data['guests']) {
                    throw ValidationException::withMessages(['request_key' => 'Mã yêu cầu đã dùng cho đơn khác.']);
                }

                return $existing;
            }
            if (! $type->is_active || $data['guests'] > $type->max_guests * $data['rooms']) {
                throw ValidationException::withMessages(['rooms' => 'Loại phòng không mở bán hoặc vượt sức chứa.']);
            }
            $nights = HotelRoomInventory::where('room_type_id', $type->id)->whereIn('stay_date', $dates)->orderBy('stay_date')->lockForUpdate()->get();
            if ($nights->count() !== count($dates) || $nights->contains(fn ($night) => $night->available_rooms < $data['rooms'])) {
                throw ValidationException::withMessages(['rooms' => 'Không còn đủ phòng cho toàn bộ kỳ lưu trú.']);
            }
            $booking = HotelBooking::create([
                ...collect($data)->only(['request_key', 'room_type_id', 'check_in', 'check_out', 'rooms', 'guests'])->all(),
                'customer_id' => $customerId, 'code' => 'HT'.Str::upper(Str::random(16)),
                'total_amount' => $nights->sum('nightly_price') * $data['rooms'],
                // A same-day hold must not carry a new booking into a past check-in date.
                'status' => 'pending', 'expires_at' => now()->addMinutes(15)->min(CarbonImmutable::parse($data['check_in'])->addDay()),
            ]);
            foreach ($nights as $night) {
                $night->decrement('available_rooms', $data['rooms']);
                $booking->nights()->create(['inventory_id' => $night->id, 'stay_date' => $night->stay_date, 'nightly_price' => $night->nightly_price, 'rooms' => $data['rooms']]);
            }

            return $booking;
        }, 3);
    }

    public function pay(HotelBooking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $locked = HotelBooking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($locked->status === 'confirmed') {
                return true;
            }
            if ($locked->status !== 'pending') {
                return false;
            }
            if ($locked->expires_at->lte(now()) || $locked->check_in->lt(today())) {
                $this->releaseLocked($locked, 'expired');

                return false;
            }
            HotelPayment::create(['hotel_booking_id' => $locked->id, 'amount' => $locked->total_amount, 'status' => 'completed', 'is_simulated' => true]);
            $locked->update(['status' => 'confirmed']);

            return true;
        }, 3);
    }

    public function cancel(HotelBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $locked = HotelBooking::query()->lockForUpdate()->findOrFail($booking->id);
            if (in_array($locked->status, ['cancelled', 'expired'], true)) {
                return;
            }
            if ($locked->status === 'pending' && ($locked->expires_at->lte(now()) || $locked->check_in->lt(today()))) {
                $this->releaseLocked($locked, 'expired');

                return;
            }
            if (! in_array($locked->status, ['pending', 'confirmed'], true) || ! $locked->check_in->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'Chỉ được hủy trước ngày nhận phòng.']);
            }
            $this->releaseLocked($locked, 'cancelled');
            $locked->payment()->where('status', 'completed')->update(['status' => 'refunded']);
        }, 3);
    }

    public function expire(): int
    {
        $count = 0;
        HotelBooking::where('status', 'pending')->where(fn ($query) => $query->where('expires_at', '<=', now())->orWhere('check_in', '<', today()->toDateString()))->select('id')->chunkById(100, function ($bookings) use (&$count) {
            foreach ($bookings as $booking) {
                $count += DB::transaction(function () use ($booking) {
                    $locked = HotelBooking::query()->lockForUpdate()->find($booking->id);
                    if (! $locked || $locked->status !== 'pending' || ($locked->expires_at->gt(now()) && $locked->check_in->gte(today()))) {
                        return 0;
                    }
                    $this->releaseLocked($locked, 'expired');

                    return 1;
                }, 3);
            }
        });

        return $count;
    }

    private function releaseLocked(HotelBooking $booking, string $status): void
    {
        HotelRoomType::query()->lockForUpdate()->findOrFail($booking->room_type_id);
        foreach ($booking->nights()->orderBy('stay_date')->get() as $night) {
            HotelRoomInventory::whereKey($night->inventory_id)->increment('available_rooms', $night->rooms);
        }
        $booking->update(['status' => $status, 'cancelled_at' => now()]);
    }
}
