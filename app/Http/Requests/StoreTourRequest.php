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

    // Ghép giờ + phút + AM/PM thành giờ 24h (ví dụ 06:00, 14:30)
    protected function prepareForValidation(): void
    {
        if ($this->filled(['time_hour', 'time_minute', 'time_meridiem'])) {
            $hour = ((int) $this->input('time_hour')) % 12;

            if ($this->input('time_meridiem') === 'PM') {
                $hour += 12;
            }

            $this->merge([
                'departure_time' => sprintf('%02d:%02d', $hour, (int) $this->input('time_minute')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'tour_id' => ['required', 'exists:tours,tour_id'],
            'departure_date' => ['required', 'date'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'max_seats' => ['required', 'integer', 'min:1', 'max:200'],
            'available_seats' => ['required', 'integer', 'min:0', 'lte:max_seats'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'child_price' => ['required', 'numeric', 'min:0'],
            'guide_id' => ['nullable', 'exists:employees,employee_id'],
            'status' => ['required', Rule::in(['open', 'full', 'cancelled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'departure_time.date_format' => 'Giờ khởi hành không hợp lệ.',
        ];
    }
}
