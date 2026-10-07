<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::withCount('bookings')->when($request->filled('q'), fn ($q) => $q->where(fn ($inner) => $inner->where('full_name', 'like', '%'.$request->string('q')->trim().'%')->orWhere('email', 'like', '%'.$request->string('q')->trim().'%')))->orderByDesc('customer_id')->paginate(15)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function update(Customer $customer): RedirectResponse
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with('success', 'Đã cập nhật trạng thái khách hàng.');
    }
}
