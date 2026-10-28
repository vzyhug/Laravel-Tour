<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartureRequest;
use App\Models\Employee;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Services\Operations\CancelDeparture;
use App\Services\Operations\SaveDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DepartureController extends Controller
{
    public function index(): View
    {
        return view('admin.departures.index', ['departures' => TourDeparture::with(['tour', 'guide'])->orderByDesc('departure_date')->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.departures.form', $this->formData(new TourDeparture(['status' => 'open'])));
    }

    public function edit(TourDeparture $departure): View
    {
        abort_if($departure->is_private, 404);

        return view('admin.departures.form', $this->formData($departure));
    }

    public function store(StoreDepartureRequest $request, SaveDeparture $saveDeparture): RedirectResponse
    {
        $data = $request->validated();
        // Keep the legacy column populated for old invoice/report code; new bookings use one fare.
        $data['child_price'] = $data['adult_price'];
        $default = config('booking.default_departure_time');

        // Chỉ Admin được đặt giờ, người khác dùng giờ mặc định
        $data['departure_time'] = $this->canEditTime(new TourDeparture)
            ? ($data['departure_time'] ?? $default)
            : $default;

        unset($data['cancellation_reason']);
        $saveDeparture->handle($data);

        return redirect()->route('admin.departures.index')->with('success', 'Đã thêm lịch khởi hành.');
    }

    public function update(StoreDepartureRequest $request, TourDeparture $departure, SaveDeparture $saveDeparture, CancelDeparture $cancelDeparture): RedirectResponse
    {
        $data = $request->validated();

        // Không có quyền, đã quá mốc khóa hoặc không gửi giờ thì giữ nguyên giờ cũ
        if (! $this->canEditTime($departure) || empty($data['departure_time'])) {
            unset($data['departure_time']);
        }

        $reason = $data['cancellation_reason'] ?? 'Nhà tổ chức hủy chuyến.';
        unset($data['cancellation_reason']);
        DB::transaction(function () use ($data, $departure, $saveDeparture, $cancelDeparture, $reason) {
            if ($data['status'] === 'cancelled') {
                // Do not persist cancelled before CancelDeparture has processed affected bookings.
                $cancelDeparture->handle($departure, (int) session('employee_id'), $reason);
            } else {
                $saveDeparture->handle($data, $departure);
            }
        }, 3);

        return redirect()->route('admin.departures.index')->with('success', 'Đã cập nhật lịch khởi hành.');
    }

    public function destroy(Request $request, TourDeparture $departure, CancelDeparture $cancelDeparture): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => ['nullable', 'string', 'max:500']]);
        $cancelDeparture->handle($departure, (int) session('employee_id'), $data['cancellation_reason'] ?? 'Nhà tổ chức hủy chuyến.');

        return back()->with('success', 'Đã hủy lịch khởi hành.');
    }

    // Chỉ Admin được sửa giờ, và chỉ khi còn cách ngày khởi hành đủ số ngày quy định
    private function canEditTime(TourDeparture $departure): bool
    {
        if (session('employee_role') !== 'Admin') {
            return false;
        }

        if (! $departure->exists) {
            return true;
        }

        $lockDays = (int) config('booking.departure_time_lock_days');

        return today()->lte($departure->departure_date->copy()->subDays($lockDays));
    }

    private function formData(TourDeparture $departure): array
    {
        return [
            'departure' => $departure,
            'canEditTime' => $this->canEditTime($departure),
            'tours' => Tour::active()->orderBy('title')->get(),
            'guides' => Employee::whereHas('role', fn ($q) => $q->where('role_name', 'Guide'))->where('is_active', true)->orderBy('full_name')->get(),
        ];
    }
}
