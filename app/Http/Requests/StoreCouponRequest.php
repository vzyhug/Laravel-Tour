<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return session('employee_role') === 'Admin';
    }

    protected function prepareForValidation(): void
    {
        $merge = ['code' => strtoupper(trim((string) $this->input('code')))];

        // Ngày dd/mm/yyyy -> yyyy-mm-dd
        foreach (['starts_on', 'expires_on'] as $field) {
            $value = trim((string) $this->input($field));
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                $merge[$field] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }

        // Tiền 5.000.000 -> 5000000
        foreach (['max_discount', 'min_order'] as $field) {
            $digits = preg_replace('/\D/', '', (string) $this->input($field));
            $merge[$field] = $digits === '' ? null : $digits;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($this->route('coupon')?->getKey(), 'coupon_id')],
            'discount_percent' => ['required', 'integer', 'between:1,100'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã giảm giá.',
            'code.unique' => 'Mã này đã tồn tại.',
            'code.regex' => 'Mã chỉ gồm chữ không dấu, số, dấu gạch ngang và gạch dưới.',
            'discount_percent.required' => 'Vui lòng nhập phần trăm giảm.',
            'discount_percent.between' => 'Phần trăm giảm phải từ 1 đến 100.',
            'starts_on.date_format' => 'Ngày bắt đầu không hợp lệ.',
            'expires_on.date_format' => 'Ngày hết hạn không hợp lệ.',
            'expires_on.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày bắt đầu.',
            'usage_limit.min' => 'Số lượt dùng tối đa phải từ 1 trở lên.',
        ];
    }
}
