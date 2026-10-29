<?php

namespace App\Support;

use App\Models\Customer;
use Closure;
use Illuminate\Validation\Rules\Password;

class CustomerValidation
{
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function normalizePhone(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^\+84[0-9]{9}$/D', $value)
            ? '0'.substr($value, 3) : $value;
    }

    public static function phoneRules(bool $unique = false, ?int $ignoreCustomer = null): array
    {
        $rules = ['required', 'string', 'regex:/^(0|\+84)[0-9]{9}$/D'];
        if ($unique) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($ignoreCustomer) {
                if (! is_string($value) || ! preg_match('/^(0|\+84)[0-9]{9}$/D', $value)) {
                    return;
                }
                $canonical = self::normalizePhone($value);
                if (Customer::whereIn('phone', [$canonical, '+84'.substr($canonical, 1)])
                    ->when($ignoreCustomer !== null, fn ($query) => $query->where('customer_id', '!=', $ignoreCustomer))->exists()) {
                    $fail('Số điện thoại này đã được sử dụng bởi tài khoản khác.');
                }
            };
        }

        return $rules;
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers(),
            function (string $attribute, mixed $value, Closure $fail) {
                // bcrypt only distinguishes the first 72 bytes, including multibyte characters.
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Mật khẩu không được vượt quá 72 byte (ký tự tiếng Việt có thể chiếm nhiều byte).');
                }
            },
        ];
    }
}
