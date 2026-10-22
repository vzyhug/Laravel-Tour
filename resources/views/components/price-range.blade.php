@props(['ceiling' => 0])
@php
    $minimum = max(0, (int) preg_replace('/\D/', '', (string) old('price_min', request('price_min'))));
    $maximum = max($minimum, (int) preg_replace('/\D/', '', (string) old('price_max', request('price_max'))));
    $ceiling = max(1000000, ceil(max($ceiling, $minimum, $maximum) / 1000000) * 1000000);
    $maximum = old('price_max', request('price_max')) === null || old('price_max', request('price_max')) === '' ? $ceiling : $maximum;
@endphp
<fieldset data-price-range>
    <legend class="label">Ngân sách / người lớn</legend>
    <div class="mb-2 flex justify-between gap-2 text-xs font-semibold text-brand-600">
        <output data-price-min-label for="price-range-min">{{ number_format($minimum, 0, ',', '.') }}₫</output>
        <span aria-hidden="true" class="text-muted">–</span>
        <output data-price-max-label for="price-range-max">{{ number_format($maximum, 0, ',', '.') }}₫</output>
    </div>
    <div class="price-range-track">
        <div class="price-range-selection" data-price-selection></div>
        <label class="sr-only" for="price-range-min">Giá thấp nhất</label>
        <input id="price-range-min" data-price-min type="range" min="0" max="{{ $ceiling }}" step="1" value="{{ $minimum }}">
        <label class="sr-only" for="price-range-max">Giá cao nhất</label>
        <input id="price-range-max" data-price-max type="range" min="0" max="{{ $ceiling }}" step="1" value="{{ $maximum }}">
    </div>
    <input type="hidden" name="price_min" value="{{ $minimum > 0 ? $minimum : '' }}">
    <input type="hidden" name="price_max" value="{{ request()->filled('price_max') || old('price_max') !== null ? $maximum : '' }}">
    <p class="mt-1 text-[10px] leading-5 text-muted">Kéo hai đầu để chọn khoảng giá</p>
    @error('price_min')<span class="mt-2 block text-xs text-red-700">{{ $message }}</span>@enderror
    @error('price_max')<span class="mt-2 block text-xs text-red-700">{{ $message }}</span>@enderror
</fieldset>
