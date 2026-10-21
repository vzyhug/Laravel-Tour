<?php

namespace App\Http\Requests;

use Carbon\Carbon;
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
            'passengers.*.date_of_birth' => ['required_if:passengers.*.passenger_type,child', 'nullable', 'date', 'before:today'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $passengers = $this->input('passengers', []);
            if (! is_array($passengers)) {
                return;
            }

            foreach ($passengers as $index => $passenger) {
                if (($passenger['passenger_type'] ?? '') === 'child' && ! empty($passenger['date_of_birth'])) {
                    $age = Carbon::parse($passenger['date_of_birth'])->age;
                    if ($age >= 12) {
                        $validator->errors()->add("passengers.{$index}.date_of_birth", 'Hành khách trẻ em phải dưới 12 tuổi.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'passengers.required' => 'Vui lòng nhập danh sách hành khách.',
            'passengers.min' => 'Chuyến đi cần ít nhất một hành khách.',
            'passengers.*.full_name.required' => 'Họ tên hành khách không được để trống.',
            'passengers.*.date_of_birth.required_if' => 'Ngày sinh là bắt buộc đối với hành khách trẻ em.',
            'passengers.*.date_of_birth.before' => 'Ngày sinh phải trước ngày hôm nay.',
        ];
    }
}
