<?php

return [
    // Khách chỉ được hủy khi còn cách ngày khởi hành tối thiểu số ngày này
    'cancel_before_days' => 5,

    // Hủy trong số giờ này kể từ lúc đặt tour thì hoàn 100%
    'full_refund_hours' => 6,

    // Hủy sau thời gian trên thì hoàn bấy nhiêu phần trăm
    'partial_refund_percent' => 70,

    // Admin chỉ được sửa giờ khởi hành khi còn cách ngày đi tối thiểu số ngày này
    'departure_time_lock_days' => 7,

    // Giờ khởi hành mặc định (định dạng 24 giờ)
    'default_departure_time' => '06:00',
];
