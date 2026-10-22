<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $request->validate([
            'departure_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'guests' => ['nullable', 'integer', 'between:1,20'],
            'province' => ['nullable', 'integer', 'exists:provinces,province_id'],
        ]);
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
            ->when($request->filled('region'), fn ($q) => $q->whereHas('province', fn ($p) => $p->where('region', $request->string('region'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('province'), fn ($q) => $q->where('province_id', $request->integer('province')))
            ->when($request->filled('departure_date') || $request->filled('guests'), fn ($q) => $q->whereHas('departures', fn ($departure) => $departure
                ->where('status', 'open')->whereDate('departure_date', '>=', today())
                ->when($request->filled('departure_date'), fn ($d) => $d->whereDate('departure_date', $request->input('departure_date')))
                ->where('available_seats', '>=', $request->integer('guests', 1))))
            ->when($min !== '', fn ($q) => $q->where('min_price', '>=', (int) $min))
            ->when($max !== '', fn ($q) => $q->where('min_price', '<=', (int) $max))
            ->when($request->filled('days'), fn ($q) => $q->where('duration_days', '<=', $request->integer('days')))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('min_price'))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('min_price'))
            ->orderBy('tour_id')->paginate(9)->withQueryString();

        return view('tours.index', ['tours' => $tours, 'categories' => TourCategory::orderBy('category_name')->get(), 'sort' => $sort]);
    }

    public function show(Request $request, Tour $tour): View
    {
        $request->validate([
            'departure_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'guests' => ['nullable', 'integer', 'between:1,20'],
        ]);
        abort_unless($tour->is_active, 404);
        $tour->load(['province', 'category', 'images', 'schedules', 'reviews.customer', 'departures' => fn ($q) => $q->where('status', 'open')->whereDate('departure_date', '>=', today())
            ->where('available_seats', '>=', $request->integer('guests', 1))
            ->when($request->filled('departure_date'), fn ($d) => $d->whereDate('departure_date', $request->input('departure_date')))
            ->orderBy('departure_date')]);

        return view('tours.show', ['tour' => $tour]);
    }

    public function province(Request $request, Province $province): View|RedirectResponse
    {
        $request->merge(['province' => $province->getKey()]);
        $response = $this->index($request);

        return $response instanceof View ? $response->with('heading', 'Tour tại '.$province->province_name) : $response;
    }
}
