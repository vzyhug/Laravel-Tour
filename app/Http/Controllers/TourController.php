<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\TourItineraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TourController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $request->validate([
            'province' => ['nullable', 'integer', 'exists:provinces,province_id'],
            'region' => ['nullable', 'string', Rule::in(['Bắc', 'Trung', 'Nam'])],
        ], [
            'province.integer' => 'Tỉnh thành không hợp lệ.',
            'province.exists' => 'Tỉnh thành đã chọn không tồn tại.',
            'region.string' => 'Vùng miền không hợp lệ.',
            'region.in' => 'Vui lòng chọn miền Bắc, Trung hoặc Nam.',
        ]);
        $selectedProvince = $request->filled('province') ? Province::findOrFail($request->integer('province')) : null;
        if ($selectedProvince && $request->filled('region') && $request->input('region') !== $selectedProvince->region) {
            $message = $selectedProvince->province_name.' thuộc miền '.$selectedProvince->region.'. Vùng miền đã chọn không khớp với tỉnh thành.';
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['region' => $message]);
            }

            return redirect()->route('tours.index', array_replace($request->query(), ['region' => $selectedProvince->region, 'page' => 1]))
                ->withErrors(['region' => $message.' Đã điều chỉnh vùng miền theo tỉnh đã chọn.']);
        }
        $prices = [];
        $priceErrors = [];
        foreach (['price_min' => 'Giá từ', 'price_max' => 'Giá đến'] as $field => $label) {
            $input = $request->input($field);
            $raw = is_scalar($input) ? trim((string) $input) : '';
            $prices[$field] = preg_replace('/[.,\s]/u', '', $raw);
            if ($input !== null && ! is_scalar($input)) {
                $priceErrors[$field] = $label.' không hợp lệ.';
            } elseif (str_contains($raw, '-')) {
                $priceErrors[$field] = $label.' không được là số âm.';
            } elseif ($raw !== '' && ! preg_match('/^[0-9][0-9.,\s]*$/uD', $raw)) {
                $priceErrors[$field] = $label.' phải là số tiền nguyên, ví dụ 5.000.000.';
            } elseif (strlen(ltrim($prices[$field], '0')) > 13) {
                $priceErrors[$field] = $label.' không được vượt quá 9.999.999.999.999₫.';
            }
        }
        $min = $prices['price_min'];
        $max = $prices['price_max'];
        if (! $priceErrors && $min !== '' && $max !== '' && (int) $min > (int) $max) {
            $priceErrors['price_max'] = 'Giá đến phải lớn hơn hoặc bằng giá từ.';
        }
        if ($priceErrors) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages($priceErrors);
            }
            $filters = array_diff_key($request->query(), array_flip(['price_min', 'price_max', 'page']));
            $oldInput = $request->query();
            foreach (['price_min', 'price_max'] as $field) {
                $oldInput[$field] = is_scalar($request->input($field)) ? $request->input($field) : '';
            }

            return redirect()->route('tours.index', $filters)->withErrors($priceErrors)->withInput($oldInput);
        }
        $sort = $request->input('sort', '');

        $tours = Tour::query()->active()->with(['province', 'category', 'images'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($selectedProvince, fn ($query) => $query->where('province_id', $selectedProvince->getKey()))
            ->when($request->filled('region'), fn ($q) => $q->whereHas('province', fn ($p) => $p->where('region', $request->string('region'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($min !== '', fn ($q) => $q->where('min_price', '>=', (int) $min))
            ->when($max !== '', fn ($q) => $q->where('min_price', '<=', (int) $max))
            ->when($request->filled('days'), fn ($q) => $q->where('duration_days', '<=', $request->integer('days')))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('min_price'))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('min_price'))
            ->orderBy('tour_id')->paginate(9)->withQueryString();

        return view('tours.index', [
            'tours' => $tours, 'categories' => TourCategory::orderBy('category_name')->get(), 'sort' => $sort,
            'provinces' => Province::orderBy('province_name')->get(), 'selectedProvince' => $selectedProvince,
            'heading' => $selectedProvince ? 'Tour tại '.$selectedProvince->province_name : null,
        ]);
    }

    public function show(Tour $tour): View
    {
        abort_unless($tour->is_active, 404);
        $tour->load(['province', 'category', 'images', 'schedules.stops.place.images', 'reviews.customer', 'departures' => fn ($q) => $q->publiclyBookable()->where('status', 'open')->whereDate('departure_date', '>=', today())->orderBy('departure_date')]);

        return view('tours.show', ['tour' => $tour, 'itineraryData' => app(TourItineraryService::class)->payload($tour, session('customer_id') ? (int) session('customer_id') : null)]);
    }

    public function province(Request $request, Province $province): View|RedirectResponse
    {
        $request->merge(['province' => $province->getKey()]);

        return $this->index($request);
    }
}
