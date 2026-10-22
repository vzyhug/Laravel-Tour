<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $tours = Tour::query()->active()->with(['province', 'category', 'images'])
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($request->filled('province'), fn ($query) => $query->where('province_id', $request->integer('province')))
            ->orderBy('tour_id')->limit(8)->get();

        return view('home', [
            'tours' => $tours,
            'provinces' => Province::orderBy('province_name')->get(),
            'categories' => TourCategory::orderBy('category_name')->get(),
            'coupons' => Coupon::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
                ->limit(4)->get(),
        ]);
    }
}
