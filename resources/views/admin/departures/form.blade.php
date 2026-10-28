@extends('layouts.admin')

@section('title', $departure->exists ? 'Sửa lịch' : 'Thêm lịch')

@php
    $current = \Carbon\Carbon::parse($departure->departure_time ?: config('booking.default_departure_time'));
    $hour = (int) old('time_hour', $current->format('g'));
    $minute = (int) old('time_minute', $current->format('i'));
    $meridiem = old('time_meridiem', $current->format('A'));

    $rawDepartureDate = old('departure_date', $departure->departure_date?->format('Y-m-d'));
    if (is_string($rawDepartureDate) && preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $rawDepartureDate, $dateMatch)) {
        $rawDepartureDate = sprintf('%04d-%02d-%02d', $dateMatch[3], $dateMatch[2], $dateMatch[1]);
    }
    if (! is_string($rawDepartureDate) || ! preg_match('#^\d{4}-\d{2}-\d{2}$#', $rawDepartureDate)) {
        $rawDepartureDate = '';
    }
    $selectedTour = $tours->firstWhere('tour_id', (int) old('tour_id', $departure->tour_id));
    $returnPreview = $rawDepartureDate && $selectedTour
        ? \Carbon\Carbon::parse($rawDepartureDate)->addDays(max(0, (int) $selectedTour->duration_days - 1))->format('d/m/Y')
        : null;

    // Hiển thị giá dạng 5.000.000
    $moneyValue = function (string $field) use ($departure) {
        $value = old($field, $departure->$field);

        return ($value === null || $value === '') ? '' : number_format((float) $value, 0, ',', '.');
    };

@endphp

@section('content')
    <a class="font-semibold text-brand-700" href="{{ route('admin.departures.index') }}">← Lịch khởi hành</a>

    <h1 class="mt-3 text-3xl font-black text-ink">
        {{ $departure->exists ? 'Chỉnh sửa lịch' : 'Thêm lịch khởi hành' }}
    </h1>

    <form class="card mt-6 grid max-w-5xl gap-7 p-5 sm:p-7" method="POST"
          action="{{ $departure->exists ? route('admin.departures.update', $departure) : route('admin.departures.store') }}">
        @csrf
        @if($departure->exists)
            @method('PUT')
        @endif

        <section class="grid gap-4 border-b border-[#eee4db] pb-6">
            <div>
                <h2 class="text-sm font-bold text-ink">Lịch trình</h2>
                <p class="mt-1 text-xs text-muted">Ngày về được tính tự động theo thời lượng tour.</p>
            </div>
            <label>
                <span class="label">Tour</span>
                <select class="field" name="tour_id" data-tour-duration>
                    @foreach($tours as $tour)
                        <option value="{{ $tour->getKey() }}" data-days="{{ $tour->duration_days }}"
                            @selected(old('tour_id', $departure->tour_id) == $tour->getKey())>
                            {{ $tour->title }} · {{ $tour->duration_days }} ngày
                        </option>
                    @endforeach
                </select>
                @error('tour_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <span class="label">Ngày khởi hành</span>
                    <input class="field" type="date" name="departure_date" data-departure-date value="{{ $rawDepartureDate }}" @if(! $departure->exists) min="{{ today()->format('Y-m-d') }}" @endif required>
                    <p class="mt-1.5 text-[10px] text-muted">Chọn ngày bắt đầu tour.</p>
                    @error('departure_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <span class="label">Ngày về dự kiến</span>
                    <div class="flex min-h-[48px] items-center justify-between gap-3 rounded-xl border border-[#eadfd4] bg-[#fcf7f3] px-4">
                        <span data-return-preview aria-live="polite" class="text-sm font-bold text-ink">{{ $returnPreview ?? 'Chọn ngày khởi hành' }}</span>
                        <span data-return-duration class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold text-muted">{{ $selectedTour ? $selectedTour->duration_days.' ngày' : 'Theo tour' }}</span>
                    </div>
                    <p class="mt-1.5 text-[10px] leading-4 text-muted">Tự động tính, bao gồm cả ngày khởi hành.</p>
                    @error('return_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="grid gap-4 border-b border-[#eee4db] pb-6 md:grid-cols-2">
            <div class="md:col-span-2"><h2 class="text-sm font-bold text-ink">Giờ và sức chứa</h2><p class="mt-1 text-xs text-muted">Thiết lập giờ tập trung và số khách tối đa cho chuyến.</p></div>

        <div>
            <span class="label">Giờ khởi hành</span>
            <div class="flex gap-2">
                <select class="field" name="time_hour" @disabled(! $canEditTime)>
                    @foreach(range(1, 12) as $h)
                        <option value="{{ $h }}" @selected($hour === $h)>{{ $h }}</option>
                    @endforeach
                </select>
                <select class="field" name="time_minute" @disabled(! $canEditTime)>
                    @foreach(range(0, 55, 5) as $mi)
                        <option value="{{ $mi }}" @selected($minute === $mi)>{{ sprintf('%02d', $mi) }}</option>
                    @endforeach
                </select>
                <select class="field" name="time_meridiem" @disabled(! $canEditTime)>
                    <option value="AM" @selected($meridiem === 'AM')>AM</option>
                    <option value="PM" @selected($meridiem === 'PM')>PM</option>
                </select>
            </div>

            @error('departure_time')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @unless($canEditTime)
                <p class="mt-1 text-xs text-slate-500">
                    @if(session('employee_role') !== 'Admin')
                        Chỉ Admin được chỉnh giờ khởi hành.
                    @else
                        Đã khóa: chuyến còn cách ngày đi dưới {{ config('booking.departure_time_lock_days') }} ngày nên không sửa giờ được.
                    @endif
                </p>
            @endunless
        </div>
        <label>
            <span class="label">Tổng số chỗ</span>
            <input class="field" type="number" name="max_seats"
                   value="{{ old('max_seats', $departure->max_seats ?? 20) }}" required>
            @error('max_seats')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </label>

        <label>
            <span class="label">Chỗ còn lại</span>
            <input class="field" type="number" name="available_seats"
                   value="{{ old('available_seats', $departure->available_seats ?? 20) }}" required>
            @error('available_seats')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </label>

        </section>

        <section class="grid gap-4 border-b border-[#eee4db] pb-6 md:grid-cols-2">
            <div class="md:col-span-2"><h2 class="text-sm font-bold text-ink">Giá và phân công</h2><p class="mt-1 text-xs text-muted">Chọn giá bán, hướng dẫn viên và trạng thái mở bán.</p></div>

        <label>
            <span class="label">Giá người lớn (₫)</span>
            <input class="field" type="text" name="adult_price" data-money-mask
                   inputmode="numeric" autocomplete="off" placeholder="5.000.000"
                   value="{{ $moneyValue('adult_price') }}" required>
            @error('adult_price')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </label>



        <label>
            <span class="label">Hướng dẫn viên</span>
            <select class="field" name="guide_id">
                <option value="">Chưa phân công</option>
                @foreach($guides as $guide)
                    <option value="{{ $guide->getKey() }}"
                        @selected(old('guide_id', $departure->guide_id) == $guide->getKey())>
                        {{ $guide->full_name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="label">Trạng thái</span>
            <select class="field" name="status">
                @foreach(['open' => 'Mở bán', 'full' => 'Đã đầy', 'cancelled' => 'Đã hủy'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $departure->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-muted">Kiểm tra lại thông tin tour và ngày về tự động trước khi lưu.</p>
            <button class="btn-primary">Lưu lịch khởi hành</button>
        </div>
    </form>

    <script>
        // Ngày về thay đổi ngay khi đổi ngày khởi hành hoặc đổi tour.
        const departureDateInput = document.querySelector('[data-departure-date]');
        const tourSelect = document.querySelector('[data-tour-duration]');
        const returnPreview = document.querySelector('[data-return-preview]');
        const returnDuration = document.querySelector('[data-return-duration]');
        const updateReturnPreview = () => {
            const days = Number(tourSelect?.selectedOptions[0]?.dataset.days || 0);
            if (returnDuration) returnDuration.textContent = days ? `${days} ngày` : 'Theo tour';
            if (!returnPreview) return;
            if (!departureDateInput?.value || !days) {
                returnPreview.textContent = 'Chọn ngày khởi hành';
                return;
            }
            const [year, month, day] = departureDateInput.value.split('-').map(Number);
            const date = new Date(Date.UTC(year, month - 1, day));
            date.setUTCDate(date.getUTCDate() + days - 1);
            returnPreview.textContent = new Intl.DateTimeFormat('vi-VN', {
                day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
            }).format(date);
        };
        departureDateInput?.addEventListener('input', updateReturnPreview);
        tourSelect?.addEventListener('change', updateReturnPreview);
        updateReturnPreview();

        // ===== Giá: gõ 5000000 tự thành 5.000.000 =====
        document.querySelectorAll('[data-money-mask]').forEach(input => {
            input.addEventListener('input', () => {
                const d = input.value.replace(/\D/g, '');
                input.value = d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            });
        });
    </script>
@endsection
