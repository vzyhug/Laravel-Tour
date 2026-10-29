<?php

namespace App\Support;

class UiText
{
    public static function status(?string $value): string
    {
        return self::label('statuses', $value);
    }

    public static function role(?string $value): string
    {
        return self::label('roles', $value);
    }

    public static function paymentStatus(?string $value): string
    {
        return $value === 'completed' ? __('ui.paid') : self::status($value);
    }

    public static function paymentMethod(?string $value): string
    {
        return self::label('payment_methods', $value);
    }

    private static function label(string $group, ?string $value): string
    {
        $key = 'ui.'.$group.'.'.$value;
        return filled($value) && \Illuminate\Support\Facades\Lang::has($key) ? __($key) : __('ui.unknown');
    }
}
