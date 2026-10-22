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
            ->when($min !== '', fn ($q) => $q->where('min_price', '>=', (int) $min))
            ->when($max !== '', fn ($q) => $q->where('min_price', '<=', (int) $max))
            ->when($request->filled('days'), fn ($q) => $q->where('duration_days', '<=', $request->integer('days')))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('min_price'))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('min_price'))
            ->orderBy('tour_id')->paginate(9)->withQueryString();

        return view('tours.index', ['tours' => $tours, 'categories' => TourCategory::orderBy('category_name')->get(), 'sort' => $sort]);
    }

    public function show(Tour $tour): View
    {
        abort_unless($tour->is_active, 404);
        $tour->load(['province', 'category', 'images', 'schedules', 'reviews.customer', 'departures' => fn ($q) => $q->where('status', 'open')->whereDate('departure_date', '>=', today())->orderBy('departure_date')]);

        return view('tours.show', ['tour' => $tour]);
    }

    public function province(Province $province): View
    {
        $tours = $province->tours()->active()->with(['province', 'category', 'images'])->paginate(9);

        return view('tours.index', ['tours' => $tours, 'categories' => TourCategory::orderBy('category_name')->get(), 'heading' => 'Tour tại '.$province->province_name]);
    }
}
