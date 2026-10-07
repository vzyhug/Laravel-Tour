<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.payments.index', ['payments' => Payment::with(['booking.customer', 'booking.departure.tour'])->orderByDesc('payment_date')->paginate(15)]);
    }
}
