<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartureRequest;
use App\Models\Employee;
use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Http\RedirectResponse;
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
        return view('admin.departures.form', $this->formData($departure));
    }

    public function store(StoreDepartureRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Keep the legacy column populated for old invoice/report code; new bookings use one fare.
        $data['child_price'] = $data['adult_price'];
        $default = config('booking.default_departure_time');

        // Chỉ Admin được đặt giờ, người khác dùng giờ mặc định
        $data['departure_time'] = $this->canEditTime(new TourDeparture())
            ? ($data['departure_time'] ?? $default)
            : $default;

        TourDeparture::create($data);

        return redirect()->route('admin.departures.index')->with('success', 'Đã thêm lịch khởi hành.');
    }

    public function update(StoreDepartureRequest $request, TourDeparture $departure): RedirectResponse
    {
        $data = $request->validated();

        // Không có quyền, đã quá mốc khóa hoặc không gửi giờ thì giữ nguyên giờ cũ
        if (! $this->canEditTime($departure) || empty($data['departure_time'])) {
            unset($data['departure_time']);
        }

        $departure->update($data);

        return redirect()->route('admin.departures.index')->with('success', 'Đã cập nhật lịch khởi hành.');
    }

    public function destroy(TourDeparture $departure): RedirectResponse
    {
        $departure->update(['status' => 'cancelled']);

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
