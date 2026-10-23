<?php

namespace App\Enums;

enum ServiceType: string
{
    case Stay = 'stay';
    case Bus = 'bus';
    case Transfer = 'transfer';
    case Rental = 'rental';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Stay => 'Lưu trú', self::Bus => 'Xe khách', self::Transfer => 'Đưa đón sân bay',
            self::Rental => 'Thuê xe', self::Activity => 'Hoạt động & vé tham quan',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Stay => 'phòng / đêm', self::Bus => 'ghế / đoạn tuyến', self::Transfer => 'xe / chuyến',
            self::Rental => 'xe / 24 giờ', self::Activity => 'người lớn / lượt',
        };
    }
}
