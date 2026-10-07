<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProvinceController extends Controller
{
    public function index(): View
    {
        return view('admin.provinces.index', ['provinces' => Province::withCount('tours')->orderBy('region')->orderBy('province_name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Province::create($request->validate(['province_name' => ['required', 'string', 'max:50', 'unique:provinces'], 'region' => ['required', Rule::in(['Bắc', 'Trung', 'Nam'])]]));

        return back()->with('success', 'Đã thêm tỉnh/thành.');
    }
}
