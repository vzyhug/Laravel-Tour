<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tour;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'tours' => Tour::count(),
                'customers' => Customer::count(),
                'bookings' => Booking::count(),
                'revenue' => Payment::whereIn('status', ['completed', 'refunded'])->sum('amount') - \App\Models\PaymentRefund::where('status', 'succeeded')->sum('amount'),
            ],
            'recentBookings' => Booking::with(['customer', 'departure.tour'])->orderByDesc('booking_date')->limit(8)->get(),
        ]);
    }
}
