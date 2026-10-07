<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTourRequest;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(): View
    {
        return view('admin.tours.index', ['tours' => Tour::with(['province', 'category'])->orderByDesc('tour_id')->paginate(12)]);
    }

    public function create(): View
    {
        return view('admin.tours.form', $this->formData(new Tour));
    }

    public function edit(Tour $tour): View
    {
        return view('admin.tours.form', $this->formData($tour));
    }

    public function store(StoreTourRequest $request): RedirectResponse
    {
        $tour = Tour::create($this->tourData($request));
        $this->storeImage($request, $tour);

        return redirect()->route('admin.tours.index')->with('success', 'Đã thêm tour.');
    }

    public function update(StoreTourRequest $request, Tour $tour): RedirectResponse
    {
        $tour->update($this->tourData($request));
        $this->storeImage($request, $tour);

        return redirect()->route('admin.tours.index')->with('success', 'Đã cập nhật tour.');
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        $tour->update(['is_active' => false]);

        return back()->with('success', 'Tour đã được ẩn khỏi trang khách hàng.');
    }

    private function formData(Tour $tour): array
    {
        return ['tour' => $tour, 'categories' => TourCategory::orderBy('category_name')->get(), 'provinces' => Province::orderBy('province_name')->get()];
    }

    private function tourData(StoreTourRequest $request): array
    {
        return array_merge($request->safe()->except('image'), ['is_active' => $request->boolean('is_active'), 'created_by' => session('employee_id')]);
    }

    private function storeImage(StoreTourRequest $request, Tour $tour): void
    {
        if (! $request->hasFile('image')) {
            return;
        }
        $path = $request->file('image')->store('tours', 'public');
        $tour->images()->update(['is_thumbnail' => false]);
        $tour->images()->create(['img_url' => Storage::url($path), 'is_thumbnail' => true]);
    }
}
