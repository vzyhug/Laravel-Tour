@if(count($itineraryData['defaults']))
<div class="mt-6" data-personal-itinerary data-image-gallery>
    <script type="application/json" data-itinerary-json>{!! json_encode($itineraryData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <button type="button" class="btn-primary" data-editor-toggle aria-expanded="false" aria-controls="personal-itinerary-panel">Tùy chỉnh lịch & gửi tư vấn</button>
    <div id="personal-itinerary-panel" class="mt-5 hidden rounded-xl border border-[#e5d9cf] bg-[#fcf8f3] p-4 sm:p-6">
        <p class="eyebrow">Một hành trình theo cách của bạn</p>
        <h3 class="mt-2 font-serif text-2xl">Sắp xếp chuyến đi mong muốn.</h3>
        <p class="mt-3 text-xs leading-7 text-muted">Trong lịch riêng, bạn có thể đổi ngày tham quan, thêm/bỏ điểm và ghi giờ dự kiến. Nhân viên sẽ kiểm tra cung đường, dịch vụ và báo giá riêng; lịch tùy chỉnh không tự thay đổi tour hay đơn đã đặt.</p>
        <p class="mt-3 text-xs leading-7 text-muted">Phân ngày theo chương trình VietGo hiện có; giờ bạn chọn là dự kiến, chưa xác nhận với điểm tham quan.</p>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            @if(session('customer_id'))<button type="button" class="btn-primary" data-itinerary-save>Lưu lịch của tôi</button><a href="{{ route('tour-itinerary.index') }}" class="text-link">Lịch đã lưu</a>
            @else<a href="{{ route('login') }}" class="btn-primary">Đăng nhập để lưu & gửi tư vấn</a>@endif
            <button type="button" class="btn-secondary" data-itinerary-reset>Khôi phục lịch gốc</button>
        </div>
        <p class="mt-3 min-h-6 text-sm" data-itinerary-status role="status" aria-live="polite"></p>
        <form data-itinerary-add class="mt-4 grid gap-3 rounded-lg bg-white p-4 sm:grid-cols-2">
            <label><span class="label">Điểm tham quan</span><select class="field" name="place_id"><option value="">Hoạt động tự ghi (ăn uống, nghỉ ngơi…)</option>@foreach($itineraryData['places'] as $place)<option value="{{ $place['id'] }}">{{ $place['name'] }}</option>@endforeach</select></label>
            <label><span class="label">Thêm vào ngày</span><select class="field" name="day_number">@foreach($itineraryData['days'] as $day)<option value="{{ $day['number'] }}">Ngày {{ $day['number'] }}</option>@endforeach</select></label>
            <label class="sm:col-span-2"><span class="label">Tên hoạt động tự ghi (chỉ cần khi không chọn địa danh)</span><input class="field" name="custom_title" maxlength="160" placeholder="Ví dụ: Nghỉ trưa cùng gia đình"></label>
            <button class="btn-secondary sm:col-span-2" type="submit">+ Thêm vào lịch</button>
        </form>
        <div class="mt-5 grid gap-6" data-itinerary-days></div>
        @if(session('customer_id'))
        @php($itineraryCustomer = \App\Models\Customer::find(session('customer_id')))
        <form data-itinerary-consult class="mt-8 border-t border-[#e5d9cf] pt-6">
            @csrf
            <h3 class="font-serif text-2xl">Gửi nhân viên tư vấn & báo giá.</h3>
            <p class="mt-3 text-xs leading-7 text-muted">Lưu lịch trước khi gửi. Yêu cầu gửi đi giữ nguyên bản lịch tại thời điểm gửi; báo giá phản hồi là tổng cho cả nhóm, chưa phải đơn đặt chỗ hay yêu cầu thanh toán.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label><span class="label">Ngày dự kiến bắt đầu</span><input type="date" name="travel_date" min="{{ today()->toDateString() }}" class="field" required></label>
                <label><span class="label">Họ tên liên hệ</span><input name="contact_name" class="field" maxlength="100" value="{{ $itineraryCustomer?->full_name }}" required></label>
                <label><span class="label">Người lớn</span><input type="number" name="adults" class="field" value="1" min="1" max="50" required></label>
                <label><span class="label">Điện thoại</span><input type="tel" name="contact_phone" class="field" maxlength="30" value="{{ $itineraryCustomer?->phone }}" required></label>
                <label><span class="label">Email</span><input type="email" name="contact_email" class="field" maxlength="255" value="{{ $itineraryCustomer?->email }}" required></label>
                <label class="sm:col-span-2"><span class="label">Mong muốn thêm (ăn uống, nơi ở…)</span><textarea name="message" class="field" maxlength="2000" rows="3"></textarea></label>
            </div>
            <button class="btn-primary mt-4" type="submit">Gửi yêu cầu tư vấn</button>
        </form>
        <div class="mt-6 grid gap-4" data-itinerary-requests></div>
        @endif
    </div>
    <noscript><p class="mt-3 text-sm">Bật JavaScript để tùy chỉnh lịch. Bạn vẫn có thể xem đầy đủ lịch gốc ở trên.</p></noscript>
</div>
@endif
