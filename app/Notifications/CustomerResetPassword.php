<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Đặt lại mật khẩu VietGo')
            ->line('Bạn vừa yêu cầu đặt lại mật khẩu tài khoản khách hàng.')
            ->action('Đặt lại mật khẩu', route('customer.password.reset', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line('Liên kết có hiệu lực trong '.config('auth.passwords.customers.expire').' phút.')
            ->line('Nếu không yêu cầu thay đổi này, bạn có thể bỏ qua email.');
    }
}
