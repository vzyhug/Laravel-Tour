<?php

namespace App\Services\Operations;

use App\Models\Employee;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDeparture
{
    public function handle(array $data, ?TourDeparture $departure = null): TourDeparture
    {
        return DB::transaction(function () use ($data, $departure) {
            if ($departure) {
                $departure = TourDeparture::lockForUpdate()->findOrFail($departure->getKey());
                abort_if($departure->is_private, 422, 'Lịch riêng theo báo giá không được sửa bằng biểu mẫu lịch chung.');
                if ($departure->status === 'cancelled' || $departure->operation_status !== 'scheduled') {
                    throw ValidationException::withMessages(['status' => 'Chuyến đã bắt đầu, hoàn thành hoặc hủy; không thể sửa lịch khởi hành.']);
                }
                if ($data['status'] === 'cancelled') {
                    throw ValidationException::withMessages(['status' => 'Hãy dùng luồng hủy chuyến để xử lý đơn và hoàn tiền.']);
                }
                $reserved = $departure->bookings()->where('status', '!=', 'cancelled')->get()
                    ->sum(fn ($booking) => $booking->total_adults + $booking->total_children);
                if ($reserved > 0) {
                    foreach (['tour_id', 'departure_date', 'return_date'] as $field) {
                        $old = $departure->{$field};
                        $old = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old;
                        if ((string) $data[$field] !== (string) $old) {
                            throw ValidationException::withMessages([$field => 'Chuyến đã có khách; hãy dùng luồng đổi lịch và thông báo cho khách.']);
                        }
                    }
                }
                if ((int) $data['max_seats'] < $reserved) {
                    throw ValidationException::withMessages(['max_seats' => 'Sức chứa không thể nhỏ hơn số chỗ đã đặt.']);
                }
                $data['available_seats'] = (int) $data['max_seats'] - $reserved;
                if ($data['available_seats'] === 0) {
                    $data['status'] = 'full';
                }
            }
            if (! empty($data['guide_id']) && $data['status'] !== 'cancelled') {
                // Serialize assignments for this guide, then recheck after taking the lock.
                $guide = Employee::with('role')->lockForUpdate()->find($data['guide_id']);
                if (! $guide || ! $guide->is_active || $guide->role->role_name !== 'Guide') {
                    throw ValidationException::withMessages(['guide_id' => 'Hãy chọn hướng dẫn viên đang hoạt động.']);
                }
                $overlap = TourDeparture::where('guide_id', $guide->getKey())->where('status', '!=', 'cancelled')
                    ->when($departure, fn ($query) => $query->whereKeyNot($departure->getKey()))
                    ->whereDate('departure_date', '<=', $data['return_date'])
                    ->whereDate('return_date', '>=', $data['departure_date'])->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['guide_id' => 'Hướng dẫn viên đã có chuyến trong khoảng ngày này.']);
                }
            }
            if ($departure) {
                $departure->fill($data)->save();
            } else {
                $departure = TourDeparture::create($data);
            }

            return $departure;
        }, 3);
    }
}
