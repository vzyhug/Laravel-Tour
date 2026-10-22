@extends('layouts.admin')

@section('title', $departure->exists ? 'Sửa lịch' : 'Thêm lịch')

@php
    $current = \Carbon\Carbon::parse($departure->departure_time ?: config('booking.default_departure_time'));
    $hour = (int) old('time_hour', $current->format('g'));
    $minute = (int) old('time_minute', $current->format('i'));
    $meridiem = old('time_meridiem', $current->format('A'));

    // Tách ngày thành [ngày, tháng, năm] để đổ vào 3 ô
    $dateParts = function (string $field) use ($departure) {
        $value = old($field);

        if ($value === null) {
            $value = $departure->$field?->format('d/m/Y');
        }

        if (is_string($value) && preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value, $m)) {
            return [$m[3], $m[2], $m[1]];
        }

        if (is_string($value) && preg_match('#^(\d{0,2})/(\d{0,2})/(\d{0,4})$#', $value, $m)) {
            return [$m[1], $m[2], $m[3]];
        }

        return ['', '', ''];
    };

    // Hiển thị giá dạng 5.000.000
    $moneyValue = function (string $field) use ($departure) {
        $value = old($field, $departure->$field);

        return ($value === null || $value === '') ? '' : number_format((float) $value, 0, ',', '.');
    };

    $dateFields = ['departure_date' => 'Ngày khởi hành', 'return_date' => 'Ngày về'];
@endphp

@section('content')
    <a class="font-semibold text-brand-700" href="{{ route('admin.departures.index') }}">← Lịch khởi hành</a>

    <h1 class="mt-3 text-3xl font-black text-ink">
        {{ $departure->exists ? 'Chỉnh sửa lịch' : 'Thêm lịch khởi hành' }}
    </h1>

    <form class="card mt-7 grid gap-5 p-6 md:grid-cols-2" method="POST"
          action="{{ $departure->exists ? route('admin.departures.update', $departure) : route('admin.departures.store') }}">
        @csrf
        @if($departure->exists)
            @method('PUT')
        @endif

        <label class="md:col-span-2">
            <span class="label">Tour</span>
            <select class="field" name="tour_id">
                @foreach($tours as $tour)
                    <option value="{{ $tour->getKey() }}" data-days="{{ $tour->duration_days }}"
                        @selected(old('tour_id', $departure->tour_id) == $tour->getKey())>
                        {{ $tour->title }} ({{ $tour->duration_days }} ngày)
                    </option>
                @endforeach
            </select>
            @error('tour_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </label>

        @foreach($dateFields as $field => $label)
            @php [$d, $m, $y] = $dateParts($field); @endphp
            <div data-date-field>
                <span class="label">{{ $label }}</span>

                <div class="field flex items-center gap-1" style="padding-top:0;padding-bottom:0;">
                    <input type="text" data-part="d" inputmode="numeric" maxlength="2" placeholder="dd"
                           autocomplete="off" value="{{ $d }}"
                           style="border:0;outline:0;background:transparent;width:2.6rem;text-align:center;padding:0.6rem 0;">
                    <span class="text-slate-400">/</span>
                    <input type="text" data-part="m" inputmode="numeric" maxlength="2" placeholder="mm"
                           autocomplete="off" value="{{ $m }}"
                           style="border:0;outline:0;background:transparent;width:2.6rem;text-align:center;padding:0.6rem 0;">
                    <span class="text-slate-400">/</span>
                    <input type="text" data-part="y" inputmode="numeric" maxlength="4" placeholder="yyyy"
                           autocomplete="off" value="{{ $y }}"
                           style="border:0;outline:0;background:transparent;width:4.2rem;text-align:center;padding:0.6rem 0;">

                    <button type="button" data-date-open title="Chọn từ lịch"
                            style="margin-left:auto;background:none;border:0;cursor:pointer;font-size:1.1rem;">📅</button>
                    <input type="date" data-date-picker tabindex="-1" aria-hidden="true"
                           style="position:absolute;opacity:0;pointer-events:none;width:0;height:0;">
                </div>

                <input type="hidden" name="{{ $field }}" data-date-value value="">

                @error($field)
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

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
            <span class="label">Giá trẻ em (₫)</span>
            <input class="field" type="text" name="child_price" data-money-mask
                   inputmode="numeric" autocomplete="off" placeholder="2.500.000"
                   value="{{ $moneyValue('child_price') }}" required>
            @error('child_price')
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

        <div class="md:col-span-2">
            <button class="btn-primary">Lưu lịch khởi hành</button>
        </div>
    </form>

    <script>
        // ===== Ngày: 3 ô dd / mm / yyyy, gõ xong tự nhảy ô =====
        document.querySelectorAll('[data-date-field]').forEach(box => {
            const parts = [...box.querySelectorAll('[data-part]')];
            const hidden = box.querySelector('[data-date-value]');
            const picker = box.querySelector('[data-date-picker]');
            const openBtn = box.querySelector('[data-date-open]');
            const limits = { d: 31, m: 12 };

            const sync = () => {
                const [d, m, y] = parts.map(p => p.value);
                hidden.value = (d || m || y) ? `${d}/${m}/${y}` : '';
            };

            const pad = p => {
                if ((p.dataset.part === 'd' || p.dataset.part === 'm') && p.value.length === 1) {
                    p.value = p.value.padStart(2, '0');
                }
            };

            parts.forEach((p, i) => {
                p.addEventListener('input', () => {
                    p.value = p.value.replace(/\D/g, '');
                    const type = p.dataset.part;

                    if (limits[type] && p.value.length === 2 && +p.value > limits[type]) {
                        p.value = String(limits[type]);
                    }

                    // Gõ số lớn (ví dụ 4 cho ngày, 2 cho tháng) thì tự thêm số 0 phía trước
                    if (limits[type] && p.value.length === 1 && +p.value > Math.floor(limits[type] / 10)) {
                        p.value = p.value.padStart(2, '0');
                    }

                    sync();

                    if (p.value.length >= p.maxLength && parts[i + 1]) {
                        parts[i + 1].focus();
                        parts[i + 1].select();
                    }
                });

                p.addEventListener('keydown', e => {
                    if (e.key === '/' || e.key === '.' || e.key === '-') {
                        e.preventDefault();
                        pad(p);
                        sync();
                        parts[i + 1]?.focus();
                    }
                    if (e.key === 'Backspace' && p.value === '' && parts[i - 1]) {
                        e.preventDefault();
                        parts[i - 1].focus();
                    }
                });

                p.addEventListener('focus', () => p.select());
                p.addEventListener('blur', () => { pad(p); sync(); });
            });

            // Nút 📅 mở lịch chọn ngày
            openBtn.addEventListener('click', () => {
                const [d, m, y] = parts.map(p => p.value);
                if (d && m && y.length === 4) {
                    picker.value = `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
                }
                try { picker.showPicker(); } catch (e) { picker.focus(); }
            });

            picker.addEventListener('change', () => {
                const [y, m, d] = picker.value.split('-');
                if (y && m && d) {
                    parts[0].value = d;
                    parts[1].value = m;
                    parts[2].value = y;
                    sync();
                }
            });

            sync();
        });

        // ===== Giá: gõ 5000000 tự thành 5.000.000 =====
        document.querySelectorAll('[data-money-mask]').forEach(input => {
            input.addEventListener('input', () => {
                const d = input.value.replace(/\D/g, '');
                input.value = d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            });
        });
    </script>
@endsection
