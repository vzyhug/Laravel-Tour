<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return session()->has('employee_id');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'exists:tour_categories,category_id'],
            'province_id' => ['required', 'exists:provinces,province_id'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:60'],
            'duration_nights' => ['required', 'integer', 'min:0', 'lte:duration_days'],
            'pickup_point' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'itinerary_summary' => ['nullable', 'string', 'max:5000'],
            'included_services' => ['nullable', 'string', 'max:5000'],
            'min_price' => ['required', 'numeric', 'min:1000'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tên tour không được để trống.',
            'category_id.required' => 'Vui lòng chọn loại tour.',
            'province_id.required' => 'Vui lòng chọn tỉnh thành.',
            'duration_days.required' => 'Số ngày không được để trống.',
            'duration_days.min' => 'Số ngày phải từ 1 ngày trở lên.',
            'duration_nights.required' => 'Số đêm không được để trống.',
            'duration_nights.lte' => 'Số đêm phải nhỏ hơn hoặc bằng số ngày của tour.',
            'min_price.required' => 'Giá từ không được để trống.',
            'min_price.min' => 'Giá từ phải lớn hơn 0.',
        ];
    }
}
