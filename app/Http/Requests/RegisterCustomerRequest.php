<?php

namespace App\Http\Requests;

use App\Support\CustomerValidation;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower($this->email) : $this->email,
            'phone' => CustomerValidation::normalizePhone($this->phone),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:customers,email'],
            'phone' => CustomerValidation::phoneRules(unique: true),
            'address' => ['nullable', 'string', 'max:255'],
            'password' => CustomerValidation::passwordRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ và tên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.required' => 'Số điện thoại không được để trống.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (VD: 0901234567).',
            'phone.unique' => 'Số điện thoại này đã được sử dụng bởi tài khoản khác.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm cả chữ và số.',
        ];
    }
}
