@props(['booking'])
<section class="card my-6 p-5 sm:p-6" aria-label="Trạng thái đặt chỗ">
    <ol class="booking-timeline text-sm">
        <li><span class="label">01 · Đặt chỗ</span><strong>{{ $booking->expired_at ? 'Hết hạn giữ chỗ' : ($booking->status === 'cancelled' ? 'Đã hủy' : 'Đã giữ chỗ') }}</strong></li>
        <li><span class="label">02 · Thanh toán mô phỏng</span><strong>{{ match($booking->payment?->status) { 'completed' => 'Đã ghi nhận', 'refunded' => 'Đã hoàn mô phỏng', default => 'Chưa ghi nhận tiền' } }}</strong></li>
        <li><span class="label">03 · Chứng từ tour</span><strong>{{ in_array($booking->status, ['confirmed', 'completed']) && $booking->payment?->status === 'completed' ? 'Chứng từ demo sẵn sàng' : ($booking->status === 'cancelled' ? 'Không còn hiệu lực' : 'Chưa cấp') }}</strong></li>
    </ol>
    @if($booking->status === 'pending' && $booking->payment_expires_at)
        <p class="mt-5 rounded-lg bg-brand-50 p-4 text-sm text-brand-700">Thanh toán trước <strong>{{ $booking->payment_expires_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }} (UTC+7)</strong> · Còn <span data-payment-countdown="{{ $booking->payment_expires_at->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" role="timer">{{ max(0, (int) now()->diffInMinutes($booking->payment_expires_at, false)) }} phút</span>. Tải lại trang không gia hạn giữ chỗ.</p>
    @elseif($booking->expired_at)
        <p class="mt-5 text-sm leading-7 text-muted">Đã hết hạn thanh toán. Chỗ giữ đã được giải phóng; hãy tìm lại để kiểm tra giá và lịch còn chỗ.</p>
        <a class="text-link mt-2" href="{{ route('tours.show', $booking->departure->tour) }}">Chọn lịch khác →</a>
    @endif
</section>
