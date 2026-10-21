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
        $departureDateRule = $this->isMethod('post')
            ? ['required', 'date', 'after_or_equal:today']
            : ['required', 'date'];

        return [
            'tour_id' => ['required', 'exists:tours,tour_id'],
            'departure_date' => $departureDateRule,
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
            'tour_id.required' => 'Vui lòng chọn tour.',
            'departure_date.required' => 'Ngày khởi hành không được để trống.',
            'departure_date.after_or_equal' => 'Ngày khởi hành phải từ ngày hôm nay trở đi.',
            'return_date.required' => 'Ngày về không được để trống.',
            'return_date.after_or_equal' => 'Ngày về phải lớn hơn hoặc bằng ngày khởi hành.',
            'max_seats.required' => 'Tổng số chỗ không được để trống.',
            'max_seats.min' => 'Tổng số chỗ phải lớn hơn 0.',
            'available_seats.lte' => 'Số chỗ còn lại không được lớn hơn tổng số chỗ.',
            'adult_price.required' => 'Giá người lớn không được để trống.',
            'child_price.required' => 'Giá trẻ em không được để trống.',
        ];
    }
}
