<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return session()->has('customer_id');
    }

    public function rules(): array
    {
        return [
            'departure_id' => ['required', 'integer', 'exists:tour_departures,departure_id'],
            'passengers' => ['required', 'array', 'min:1', 'max:20'],
            'passengers.*' => ['array'],
            'passengers.*.full_name' => ['required', 'string', 'max:100'],
            'passengers.*.id_card' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z]{8,20}$/'],
            'passengers.*.date_of_birth' => ['nullable', 'date', 'before:today'],
            'pickup_type' => ['required', 'in:self,door'],
            'pickup_address' => ['nullable', 'required_if:pickup_type,door', 'string', 'max:255'],
            'pickup_note' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'passengers.required' => 'Vui lòng nhập danh sách hành khách.',
            'passengers.min' => 'Chuyến đi cần ít nhất một hành khách.',
            'passengers.*.full_name.required' => 'Họ tên hành khách không được để trống.',
            'passengers.*.id_card.required' => 'CCCD hoặc Hộ chiếu là bắt buộc đối với mỗi hành khách.',
            'passengers.*.id_card.regex' => 'CCCD hoặc Hộ chiếu phải từ 8 đến 20 ký tự chữ và số.',
            'passengers.*.date_of_birth.before' => 'Ngày sinh phải trước ngày hôm nay.',
            'pickup_address.required_if' => 'Vui lòng nhập địa chỉ đón.',
            'pickup_address.max' => 'Địa chỉ đón không được quá 255 ký tự.',
            'pickup_note.max' => 'Ghi chú không được quá 255 ký tự.',
            'pickup_type.required' => 'Vui lòng chọn cách tham gia chuyến đi.',
            'pickup_type.in' => 'Cách tham gia chuyến đi không hợp lệ.',
        ];
    }
}
