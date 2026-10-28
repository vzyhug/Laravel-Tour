<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\TourItineraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $request->validate([
            'province' => ['nullable', 'integer', 'exists:provinces,province_id'],
            'region' => ['nullable', 'string', Rule::in(['Bắc', 'Trung', 'Nam'])],
        ], [
            'province.integer' => 'Tỉnh thành không hợp lệ.',
            'province.exists' => 'Tỉnh thành đã chọn không tồn tại.',
            'region.string' => 'Vùng miền không hợp lệ.',
            'region.in' => 'Vui lòng chọn miền Bắc, Trung hoặc Nam.',
        ]);
        $selectedProvince = $request->filled('province') ? Province::findOrFail($request->integer('province')) : null;
        if ($selectedProvince && $request->filled('region') && $request->input('region') !== $selectedProvince->region) {
            $message = $selectedProvince->province_name.' thuộc miền '.$selectedProvince->region.'. Vùng miền đã chọn không khớp với tỉnh thành.';
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['region' => $message]);
            }

            return redirect()->route('tours.index', array_replace($request->query(), ['region' => $selectedProvince->region, 'page' => 1]))
                ->withErrors(['region' => $message.' Đã điều chỉnh vùng miền theo tỉnh đã chọn.']);
        }
        $rawMin = (string) $request->input('price_min');
        $rawMax = (string) $request->input('price_max');

        if ($rawMin !== '' && str_contains($rawMin, '-')) {
            return redirect()->route('tours.index')
                ->withErrors(['price_min' => 'Giá từ không được là số âm.'])
                ->withInput();
        }
        if ($rawMax !== '' && str_contains($rawMax, '-')) {
            return redirect()->route('tours.index')
                ->withErrors(['price_max' => 'Giá đến không được là số âm.'])
                ->withInput();
        }

        $min = preg_replace('/\D/', '', $rawMin);
        $max = preg_replace('/\D/', '', $rawMax);

        $descendingRange = $min !== '' && $max !== '' && (int) $min > (int) $max;
        if ($descendingRange) {
            [$min, $max] = [$max, $min];
        }
        $sort = $request->input('sort') ?: ($descendingRange ? 'price_desc' : '');

        $tours = Tour::query()->active()->with(['province', 'category', 'images'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($selectedProvince, fn ($query) => $query->where('province_id', $selectedProvince->getKey()))
            ->when($request->filled('region'), fn ($q) => $q->whereHas('province', fn ($p) => $p->where('region', $request->string('region'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($min !== '', fn ($q) => $q->where('min_price', '>=', (int) $min))
            ->when($max !== '', fn ($q) => $q->where('min_price', '<=', (int) $max))
            ->when($request->filled('days'), fn ($q) => $q->where('duration_days', '<=', $request->integer('days')))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('min_price'))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('min_price'))
            ->orderBy('tour_id')->paginate(9)->withQueryString();

        return view('tours.index', [
            'tours' => $tours, 'categories' => TourCategory::orderBy('category_name')->get(), 'sort' => $sort,
            'provinces' => Province::orderBy('province_name')->get(), 'selectedProvince' => $selectedProvince,
            'heading' => $selectedProvince ? 'Tour tại '.$selectedProvince->province_name : null,
        ]);
    }

    public function show(Tour $tour): View
    {
        abort_unless($tour->is_active, 404);
        $tour->load(['province', 'category', 'images', 'schedules', 'reviews.customer', 'departures' => fn ($q) => $q->where('status', 'open')->whereDate('departure_date', '>=', today())->orderBy('departure_date')]);

        return view('tours.show', ['tour' => $tour, 'itineraryData' => app(TourItineraryService::class)->payload($tour, session('customer_id') ? (int) session('customer_id') : null)]);
    }

    public function province(Request $request, Province $province): View|RedirectResponse
    {
        $request->merge(['province' => $province->getKey()]);

        return $this->index($request);
    }
}
