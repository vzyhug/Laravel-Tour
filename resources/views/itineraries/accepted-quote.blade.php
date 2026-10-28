@if($booking->consultation_id && $booking->quote_snapshot)
    @php($acceptedQuote = $booking->quote_snapshot)
    <section class="card mt-6 p-6" aria-label="Lịch trình và báo giá đã chấp nhận">
        <h2 class="font-serif text-2xl">Lịch trình riêng đã chấp nhận</h2>
        <p class="mt-3 text-sm leading-7">{{ $acceptedQuote['tour'] }} · {{ \Carbon\Carbon::parse($acceptedQuote['travel_date'])->format('d/m/Y') }} · {{ $acceptedQuote['adults'] + $acceptedQuote['children'] }} khách</p>
        <p class="mt-3 font-semibold">Tổng giá cả nhóm: {{ number_format($acceptedQuote['quoted_total'], 0, ',', '.') }}₫</p>
        <p class="mt-3 whitespace-pre-line text-sm leading-7">{{ $acceptedQuote['reply'] }}</p>
        @foreach(collect($acceptedQuote['items'] ?? [])->groupBy('day_number')->sortKeys() as $day => $items)
            <h3 class="mt-5 font-semibold">Ngày {{ $day }}</h3>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach($items as $item)
                    <li>
                        <strong>{{ $item['title'] }}</strong>
                        @if(!empty($item['start_time']))
                            · {{ substr($item['start_time'], 0, 5) }}
                        @endif
                        @if(!empty($item['duration_minutes']))
                            · {{ $item['duration_minutes'] }} phút
                        @endif
                        @if(!empty($item['notes']))
                            <p class="mt-1 whitespace-pre-line text-muted">{{ $item['notes'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
        <p class="mt-5 text-xs leading-6 text-muted">Nội dung được lưu theo báo giá #{{ $acceptedQuote['consultation_id'] }}, phiên bản {{ $acceptedQuote['consultation_version'] }}, tại thời điểm chấp nhận. Việc sửa lịch trình cá nhân sau đó không thay đổi đơn này.</p>
    </section>
@endif
