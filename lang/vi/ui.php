<?php

return [
    'unknown' => 'Chưa xác định',
    'paid' => 'Đã thanh toán',
    'statuses' => [
        'pending' => 'Đang chờ xử lý', 'confirmed' => 'Đã xác nhận',
        'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy',
        'expired' => 'Đã hết hạn', 'failed' => 'Thất bại',
        'succeeded' => 'Thành công', 'processing' => 'Đang xử lý',
        'requested' => 'Đã yêu cầu', 'refunded' => 'Đã hoàn tiền',
        'paid' => 'Đã thanh toán', 'review' => 'Cần kiểm tra',
        'matched' => 'Khớp dữ liệu', 'mismatch' => 'Có sai lệch',
        'scheduled' => 'Sắp khởi hành', 'in_progress' => 'Đang diễn ra',
        'open' => 'Đang nhận khách', 'full' => 'Đã đóng nhận khách',
        'superseded' => 'Đã thay thế',
    ],
    'roles' => ['Admin' => 'Quản trị viên', 'Guide' => 'Hướng dẫn viên', 'Customer' => 'Khách hàng'],
    'payment_methods' => [
        'bank_transfer' => 'Chuyển khoản ngân hàng', 'sepay' => 'QR ngân hàng · SePay',
        'cash' => 'Thanh toán tại văn phòng', 'card' => 'Thẻ tín dụng / ghi nợ',
        'momo' => 'Ví MoMo', 'vietqr_demo' => 'VietQR (mô phỏng)',
    ],
];
