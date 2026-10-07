<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(Request $request): View
    {
        $tours = Tour::query()->active()->with(['province', 'category', 'images'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($request->filled('region'), fn ($q) => $q->whereHas('province', fn ($p) => $p->where('region', $request->string('region'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->orderBy('tour_id')->paginate(9)->withQueryString();

        return view('tours.index', ['tours' => $tours, 'categories' => TourCategory::orderBy('category_name')->get()]);
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
