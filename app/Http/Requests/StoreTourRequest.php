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
            'duration_nights' => ['required', 'integer', 'min:0', 'max:59'],
            'pickup_point' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'itinerary_summary' => ['nullable', 'string', 'max:5000'],
            'included_services' => ['nullable', 'string', 'max:5000'],
            'min_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
