<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(): View
    {
        $customer = Customer::findOrFail(session('customer_id'));
        $tours = $customer->favoriteTours()->active()->with(['province', 'category', 'images'])->get();

        return view('favorites.index', ['tours' => $tours]);
    }

    public function toggle(Tour $tour): RedirectResponse
    {
        $customer = Customer::findOrFail(session('customer_id'));
        $customer->favoriteTours()->toggle($tour->getKey());

        return back();
    }
}
