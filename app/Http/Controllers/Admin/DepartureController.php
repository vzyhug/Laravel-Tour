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
        TourDeparture::create($request->validated());

        return redirect()->route('admin.departures.index')->with('success', 'Đã thêm lịch khởi hành.');
    }

    public function update(StoreDepartureRequest $request, TourDeparture $departure): RedirectResponse
    {
        $departure->update($request->validated());

        return redirect()->route('admin.departures.index')->with('success', 'Đã cập nhật lịch khởi hành.');
    }

    public function destroy(TourDeparture $departure): RedirectResponse
    {
        $departure->update(['status' => 'cancelled']);

        return back()->with('success', 'Đã hủy lịch khởi hành.');
    }

    private function formData(TourDeparture $departure): array
    {
        return ['departure' => $departure, 'tours' => Tour::active()->orderBy('title')->get(), 'guides' => Employee::whereHas('role', fn ($q) => $q->where('role_name', 'Guide'))->where('is_active', true)->orderBy('full_name')->get()];
    }
}
