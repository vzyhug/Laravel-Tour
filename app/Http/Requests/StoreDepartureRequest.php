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

    protected function prepareForValidation(): void
    {
        $merge = [];

        // Ngày nhập dạng dd/mm/yyyy -> đổi về yyyy-mm-dd để lưu
        foreach (['departure_date', 'return_date'] as $field) {
            $value = trim((string) $this->input($field));

            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                $merge[$field] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }

        // Giá nhập dạng 5.000.000 -> bỏ dấu chấm, chỉ giữ chữ số
        foreach (['adult_price', 'child_price'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = preg_replace('/\D/', '', (string) $this->input($field));
            }
        }

        // Ghép giờ + phút + AM/PM thành giờ 24h (ví dụ 06:00, 14:30)
        if ($this->filled(['time_hour', 'time_minute', 'time_meridiem'])) {
            $hour = ((int) $this->input('time_hour')) % 12;

            if ($this->input('time_meridiem') === 'PM') {
                $hour += 12;
            }

            $merge['departure_time'] = sprintf('%02d:%02d', $hour, (int) $this->input('time_minute'));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'tour_id' => ['required', 'exists:tours,tour_id'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
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
            'departure_date.date_format' => 'Ngày khởi hành không hợp lệ, hãy nhập dạng dd/mm/yyyy.',
            'departure_date.required' => 'Vui lòng nhập ngày khởi hành.',
            'return_date.date_format' => 'Ngày về không hợp lệ, hãy nhập dạng dd/mm/yyyy.',
            'return_date.required' => 'Vui lòng nhập ngày về.',
            'return_date.after_or_equal' => 'Ngày về phải sau hoặc bằng ngày khởi hành.',
            'adult_price.required' => 'Vui lòng nhập giá người lớn.',
            'adult_price.numeric' => 'Giá người lớn không hợp lệ.',
            'child_price.required' => 'Vui lòng nhập giá trẻ em.',
            'child_price.numeric' => 'Giá trẻ em không hợp lệ.',
        ];
    }
}
