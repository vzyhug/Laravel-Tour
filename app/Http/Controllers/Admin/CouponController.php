<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::withCount(['bookings as used_count' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->orderByDesc('coupon_id')
            ->paginate(15);

        return view('admin.coupons.index', ['coupons' => $coupons]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['is_active' => true])]);
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['min_order'] = $data['min_order'] ?? 0;

        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Đã thêm mã giảm giá.');
    }

    public function update(StoreCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = $request->validated();
        $data['min_order'] = $data['min_order'] ?? 0;

        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Đã cập nhật mã giảm giá.');
    }
}
