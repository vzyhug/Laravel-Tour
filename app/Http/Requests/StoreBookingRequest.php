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
            'passengers.*.full_name' => ['required', 'string', 'max:100'],
            'passengers.*.passenger_type' => ['required', 'in:adult,child'],
            'passengers.*.id_card' => ['nullable', 'string', 'max:20'],
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
            'pickup_address.required_if' => 'Vui lòng nhập địa chỉ đón.',
            'pickup_address.max' => 'Địa chỉ đón không được quá 255 ký tự.',
            'pickup_note.max' => 'Ghi chú không được quá 255 ký tự.',
            'pickup_type.required' => 'Vui lòng chọn cách tham gia chuyến đi.',
            'pickup_type.in' => 'Cách tham gia chuyến đi không hợp lệ.',
        ];
}
}
