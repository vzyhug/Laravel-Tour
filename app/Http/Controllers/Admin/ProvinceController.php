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
    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('q', ''));

        $provinces = Province::withCount('tours')
            ->when($keyword !== '', fn ($query) => $query->where('province_name', 'like', "%{$keyword}%"))
            ->orderBy('region')
            ->orderBy('province_name')
            ->get();

        return view('admin.provinces.index', [
            'provinces' => $provinces,
            'keyword' => $keyword,
        ]);
    }

    public function edit(Province $province): View
    {
        return view('admin.provinces.edit', ['province' => $province]);
    }

    public function update(Request $request, Province $province): RedirectResponse
    {
        $province->update($request->validate([
            'province_name' => ['required', 'string', 'max:50', Rule::unique('provinces', 'province_name')->ignore($province)],
            'region' => ['required', Rule::in(['Bắc', 'Trung', 'Nam'])],
        ]));

        return redirect()->route('admin.provinces.index')->with('success', 'Đã cập nhật tỉnh/thành.');
    }
}
