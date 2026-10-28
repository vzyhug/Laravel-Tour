<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerTransaction extends Notification
{
    public function __construct(public string $event, public array $payload) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $title = match ($this->event) {
            'booking.created' => 'Đã tiếp nhận đơn đặt tour',
            'booking.cancelled' => 'Đơn đặt tour đã hủy',
            'booking.expired' => 'Đơn đặt tour đã hết hạn giữ chỗ',
            'payment.failed' => 'Thanh toán chưa thành công',
            'refund.succeeded' => 'Cập nhật giao dịch hoàn tiền',
            'payment.completed' => 'Thanh toán thành công',
            'payment.refunded' => 'Cập nhật trạng thái hoàn tiền',
            'departure.changed' => 'Lịch khởi hành có thay đổi',
            'departure.cancelled' => 'Chuyến đi đã bị hủy',
            'departure.reminder' => 'Nhắc lịch khởi hành',
            'consultation.quoted' => 'Bạn có báo giá lịch trình mới',
            default => 'Cập nhật chuyến đi VietGo',
        };
        $mail = (new MailMessage)->subject($title)->greeting('Xin chào '.$notifiable->full_name)->line($title.'.');
        if (isset($this->payload['booking_id'])) {
            $mail->line('Mã đơn: '.$this->payload['booking_code'])->action('Xem đơn đặt tour', route('customer.bookings.show', $this->payload['booking_id']));
        } elseif (isset($this->payload['consultation_id'])) {
            $mail->action('Xem báo giá', route('itinerary-consultations.show', $this->payload['consultation_id']));
        }

        return $mail;
    }
}
