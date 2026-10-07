<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return session()->has('employee_id');
    }

    public function rules(): array
    {
        return [
            'tour_id' => ['required', 'exists:tours,tour_id'],
            'departure_date' => ['required', 'date'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'max_seats' => ['required', 'integer', 'min:1', 'max:200'],
            'available_seats' => ['required', 'integer', 'min:0', 'lte:max_seats'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'child_price' => ['required', 'numeric', 'min:0'],
            'guide_id' => ['nullable', 'exists:employees,employee_id'],
            'status' => ['required', Rule::in(['open', 'full', 'cancelled'])],
        ];
    }
}
