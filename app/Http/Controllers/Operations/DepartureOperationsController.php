<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\BookingPassenger;
use App\Models\DepartureReport;
use App\Models\Employee;
use App\Models\PassengerAttendance;
use App\Models\TourDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DepartureOperationsController extends Controller
{
    private function actor(): Employee
    {
        $employee = Employee::with('role')->find(session('employee_id'));
        abort_unless($employee && $employee->is_active && in_array($employee->role->role_name, ['Admin', 'Guide'], true), 403);

        return $employee;
    }

    private function authorizeDeparture(TourDeparture $departure): Employee
    {
        $actor = $this->actor();
        abort_if($actor->role->role_name === 'Guide' && (int) $departure->guide_id !== (int) $actor->getKey(), 403);

        return $actor;
    }

    public function index(Request $request): View
    {
        $actor = $this->actor();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'trip_status' => ['nullable', 'in:scheduled,in_progress,ended,completed,cancelled'],
        ]);

        $departures = TourDeparture::with(['tour', 'guide'])
            ->withCount('confirmedPassengers')
            ->when($actor->role->role_name === 'Guide', fn ($q) => $q->where('guide_id', $actor->getKey()))
            ->when(filled($filters['q'] ?? null), fn ($q) => $q->whereHas('tour', fn ($tour) => $tour->where('title', 'like', '%'.trim($filters['q']).'%')))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('departure_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('departure_date', '<=', $filters['to']))
            ->when(filled($filters['trip_status'] ?? null), fn ($q) => $q->effectiveTripStatus($filters['trip_status']))
            ->orderByDesc('departure_date')->paginate(15)->withQueryString();

        return view('operations.index', compact('departures', 'filters'));
    }

    public function show(TourDeparture $departure): View
    {
        $this->authorizeDeparture($departure);
        $departure->load(['tour', 'guide', 'bookings' => fn ($q) => $q->whereIn('status', ['confirmed', 'completed'])->with(['customer', 'passengers'])]);
        $passengerIds = $departure->bookings->flatMap(fn ($booking) => $booking->passengers->modelKeys());
        $attendance = PassengerAttendance::whereIn('passenger_id', $passengerIds)->get()->keyBy('passenger_id');
        $report = DepartureReport::where('departure_id', $departure->getKey())->first();

        $reviews = \App\Models\Review::with('customer')
            ->whereHas('booking', fn ($query) => $query->where('departure_id', $departure->getKey()))
            ->latest('created_at')->get();

        return view('operations.show', compact('departure', 'attendance', 'report', 'reviews'));
    }

    public function attendance(Request $request, TourDeparture $departure, BookingPassenger $passenger): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:present,absent'], 'notes' => ['nullable', 'string', 'max:500']]);
        $this->authorizeDeparture($departure);
        if ($departure->departure_date->isFuture()) {
            return back()->with('warning', 'Chưa đến ngày khởi hành. Bạn có thể điểm danh từ ngày bắt đầu chuyến.');
        }

        DB::transaction(function () use ($departure, $passenger, $data) {
            $departure = TourDeparture::lockForUpdate()->findOrFail($departure->getKey());
            $actor = $this->authorizeDeparture($departure);
            abort_if($departure->status === 'cancelled' || $departure->operation_status === 'completed', 409, 'Chuyến đã đóng.');
            abort_if($departure->departure_date->isFuture(), 422, 'Chưa đến ngày khởi hành.');
            $booking = $passenger->booking()->lockForUpdate()->firstOrFail();
            abort_unless((int) $booking->departure_id === (int) $departure->getKey() && $booking->status === 'confirmed', 422, 'Hành khách không thuộc đơn hợp lệ của chuyến này.');
            PassengerAttendance::updateOrCreate(['passenger_id' => $passenger->getKey()], [
                ...$data, 'checked_by' => $actor->getKey(), 'checked_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Đã lưu điểm danh.');
    }

    public function status(Request $request, TourDeparture $departure): RedirectResponse
    {
        $data = $request->validate(['operation_status' => ['required', 'in:in_progress,completed']]);
        $this->authorizeDeparture($departure);

        if ($departure->status === 'cancelled') {
            return back()->with('warning', 'Chuyến đã hủy nên không thể cập nhật trạng thái.');
        }

        $next = ['scheduled' => 'in_progress', 'in_progress' => 'completed'];
        if (($next[$departure->operation_status] ?? null) !== $data['operation_status']) {
            return back()->with('warning', 'Trạng thái chuyến đã thay đổi. Tải lại trang rồi thử lại.');
        }
        if ($departure->departure_date->isFuture()) {
            return back()->with('warning', 'Chưa đến ngày khởi hành. Bạn có thể bắt đầu chuyến từ ngày khởi hành.');
        }
        if ($data['operation_status'] === 'completed') {
            if ($departure->return_date->isFuture()) {
                return back()->with('warning', 'Chưa đến ngày kết thúc chuyến nên chưa thể hoàn thành.');
            }

            $confirmedBookings = $departure->bookings()->where('status', 'confirmed')->orderBy('booking_id')->get();
            $passengerIds = BookingPassenger::whereIn('booking_id', $confirmedBookings->modelKeys())->pluck('passenger_id');
            if (PassengerAttendance::whereIn('passenger_id', $passengerIds)->count() !== $passengerIds->count()) {
                return back()->with('warning', 'Hãy điểm danh đầy đủ hành khách trước khi hoàn thành chuyến.');
            }
        }

        DB::transaction(function () use ($departure, $data) {
            $departure = TourDeparture::lockForUpdate()->findOrFail($departure->getKey());
            $this->authorizeDeparture($departure);
            abort_if($departure->status === 'cancelled', 409, 'Chuyến đã hủy.');
            $next = ['scheduled' => 'in_progress', 'in_progress' => 'completed'];
            abort_unless(($next[$departure->operation_status] ?? null) === $data['operation_status'], 409, 'Chuyển trạng thái không hợp lệ.');
            abort_if($departure->departure_date->isFuture(), 422, 'Chưa đến ngày khởi hành.');
            if ($data['operation_status'] === 'completed') {
                abort_if($departure->return_date->isFuture(), 422, 'Chưa đến ngày kết thúc chuyến.');
                $bookings = $departure->bookings()->where('status', 'confirmed')->orderBy('booking_id')->lockForUpdate()->get();
                $passengerIds = BookingPassenger::whereIn('booking_id', $bookings->modelKeys())->pluck('passenger_id');
                abort_if(PassengerAttendance::whereIn('passenger_id', $passengerIds)->count() !== $passengerIds->count(), 422, 'Hãy điểm danh đầy đủ hành khách trước khi hoàn thành.');
                foreach ($bookings as $booking) {
                    $booking->update(['status' => 'completed']);
                }
            }
            // Stop public sales even when the departure still has unused seats.
            $departure->forceFill([...$data, 'status' => 'full'])->save();
        }, 3);

        return back()->with('success', 'Đã cập nhật trạng thái chuyến.');
    }

    public function report(Request $request, TourDeparture $departure): RedirectResponse
    {
        $data = $request->validate(['summary' => ['required', 'string', 'max:10000'], 'incidents' => ['nullable', 'string', 'max:10000']]);
        DB::transaction(function () use ($departure, $data) {
            $departure = TourDeparture::lockForUpdate()->findOrFail($departure->getKey());
            $actor = $this->authorizeDeparture($departure);
            abort_unless($departure->status !== 'cancelled' && $departure->operation_status === 'completed', 422, 'Chỉ gửi báo cáo sau khi hoàn thành chuyến.');
            DepartureReport::updateOrCreate(['departure_id' => $departure->getKey()], [
                ...$data, 'submitted_by' => $actor->getKey(), 'submitted_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Đã gửi báo cáo chuyến đi.');
    }
}
