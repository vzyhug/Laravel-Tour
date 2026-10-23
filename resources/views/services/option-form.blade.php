@php
    $tomorrow = today('Asia/Ho_Chi_Minh')->addDay();
    $trips = $option->resource->trips->where('is_active', true)->filter(fn ($trip) => $trip->starts_at->isFuture());
    $tripData = $trips->map(fn ($trip) => ['id' => $trip->id, 'label' => $trip->code.' · '.$trip->starts_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y').' → '.$trip->ends_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y'), 'stops' => $trip->stops->map(fn ($stop) => ['id' => $stop->id, 'name' => $stop->name, 'address' => $stop->address, 'time' => $stop->time_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y')])->values()])->values();
@endphp
<form method="POST" action="{{ route('services.quotes.store', $option) }}" class="mt-6 grid gap-4 border-t border-slate-200 pt-6 sm:grid-cols-2 lg:grid-cols-3" @if($product->type === 'bus') data-bus-form data-old-seats="{{ json_encode(old('seats', [])) }}" data-old-from="{{ old('boarding_stop_id') }}" data-old-to="{{ old('alighting_stop_id') }}" data-trips="{{ json_encode($tripData) }}" data-seats-base="{{ url('/service-trips') }}" @endif>
    @csrf
    @if($product->type === 'stay')
        <label><span class="label">Nhận phòng</span><input class="field" type="date" name="check_in" min="{{ today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ old('check_in', request('check_in', $tomorrow->toDateString())) }}" required></label>
        <label><span class="label">Trả phòng</span><input class="field" type="date" name="check_out" value="{{ old('check_out', request('check_out', $tomorrow->copy()->addDay()->toDateString())) }}" required></label>
        <label><span class="label">Số phòng</span><input class="field" type="number" name="rooms" min="1" max="5" value="{{ old('rooms', request('rooms', 1)) }}" required></label>
        <label><span class="label">Người lớn</span><input class="field" type="number" name="adults" min="1" max="20" value="{{ old('adults', request('adults', 1)) }}" required></label>
        <label><span class="label">Trẻ em</span><input class="field" type="number" name="children" min="0" max="10" value="{{ old('children', request('children', 0)) }}" required></label>
    @elseif($product->type === 'bus')
        <label class="lg:col-span-3"><span class="label">Chuyến xe · cả ngày đi và ngày đến (UTC+7)</span><select class="field" name="trip_id" data-bus-trip required>@foreach($tripData as $trip)<option value="{{ $trip['id'] }}" @selected(old('trip_id') == $trip['id'])>{{ $trip['label'] }}</option>@endforeach</select></label>
        <label><span class="label">Điểm lên</span><select class="field" name="boarding_stop_id" data-bus-from required></select></label>
        <label><span class="label">Điểm xuống</span><select class="field" name="alighting_stop_id" data-bus-to required></select></label>
        <fieldset class="sm:col-span-2 lg:col-span-3"><legend class="label">Chọn ghế (tối đa 8) · Trống / Đang giữ / Đã bán</legend><div class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-bus-seats></div><p class="mt-3 text-sm text-muted" data-bus-status role="status">Chọn điểm lên/xuống để kiểm tra ghế.</p></fieldset>
    @elseif($product->type === 'transfer')
        <label><span class="label">Chiều đưa đón</span><select class="field" name="direction" required><option value="airport_to_address" @selected(old('direction') === 'airport_to_address')>Sân bay → địa chỉ</option><option value="address_to_airport" @selected(old('direction') === 'address_to_airport')>Địa chỉ → sân bay</option></select></label>
        <label><span class="label">Sân bay</span><input class="field" value="{{ $option->attributes['airport'] ?? '' }}" readonly></label>
        <label><span class="label">Giờ đón (UTC+7)</span><input class="field" type="datetime-local" name="pickup_at" value="{{ old('pickup_at', request('pickup_at', $tomorrow->copy()->setTime(9,0)->format('Y-m-d\TH:i'))) }}" required></label>
        <label><span class="label">Vùng phục vụ demo</span><select class="field" name="zone" required>@foreach($option->attributes['zones'] ?? [] as $zone => $fee)<option value="{{ $zone }}" @selected(old('zone') === $zone)>{{ $zone }} · Phụ phí {{ number_format($fee, 0, ',', '.') }}₫</option>@endforeach</select></label>
        <label class="sm:col-span-2"><span class="label">Địa chỉ cụ thể trong vùng đã chọn</span><input class="field" name="address" value="{{ old('address') }}" maxlength="255" required></label>
        <label><span class="label">Số người</span><input class="field" type="number" name="passengers" min="1" max="{{ $option->occupancy }}" value="{{ old('passengers', 1) }}" required></label>
        <label><span class="label">Số kiện hành lý</span><input class="field" type="number" name="bags" min="0" max="{{ $option->attributes['max_bags'] ?? 0 }}" value="{{ old('bags', 0) }}" required></label>
        <label><span class="label">Mã chuyến bay (nếu có)</span><input class="field" name="flight_number" maxlength="15" value="{{ old('flight_number') }}"></label>
    @elseif($product->type === 'rental')
        <label><span class="label">Nhận xe (UTC+7)</span><input class="field" type="datetime-local" name="pickup_at" value="{{ old('pickup_at', request('pickup_at', $tomorrow->copy()->setTime(9,0)->format('Y-m-d\TH:i'))) }}" required></label>
        <label><span class="label">Trả xe (UTC+7)</span><input class="field" type="datetime-local" name="return_at" value="{{ old('return_at', request('return_at', $tomorrow->copy()->addDay()->setTime(9,0)->format('Y-m-d\TH:i'))) }}" required></label>
        <label><span class="label">Số người (gồm người lái)</span><input class="field" type="number" name="passengers" min="1" max="{{ $option->occupancy }}" value="{{ old('passengers', request('passengers', 1)) }}" required></label>
    @else
        <label><span class="label">Ngày sử dụng</span><input class="field" type="date" name="visit_date" min="{{ today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ old('visit_date', request('visit_date', $tomorrow->toDateString())) }}" required></label>
        <label><span class="label">Khung giờ</span><select class="field" name="slot" required>@foreach($option->attributes['slots'] ?? [] as $slot)<option value="{{ $slot }}" @selected(old('slot') === $slot)>{{ $slot }} (UTC+7)</option>@endforeach</select></label>
        <label><span class="label">Người lớn</span><input class="field" type="number" name="adults" min="1" max="20" value="{{ old('adults', request('adults', 1)) }}" required></label>
        <label><span class="label">Trẻ em theo nhóm tuổi gói</span><input class="field" type="number" name="children" min="0" max="20" value="{{ old('children', request('children', 0)) }}" required></label>
    @endif
    <div class="flex flex-wrap items-center gap-4 sm:col-span-2 lg:col-span-3"><button class="btn-primary" @disabled($product->type === 'bus' && $trips->isEmpty())>Kiểm tra giá & tiếp tục</button><span class="text-xs text-muted">Cần đăng nhập để lấy báo giá. Chưa giữ chỗ ở bước này.</span></div>
</form>
