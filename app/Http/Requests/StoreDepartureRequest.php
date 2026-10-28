<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Tour;
use App\Models\TourDeparture;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return session()->has('employee_id') && Employee::whereKey(session('employee_id'))->where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('role_name', ['Admin', 'Staff']))->exists();
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
        foreach (['adult_price'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = str_replace(['.', ',', ' '], '', trim((string) $this->input($field)));
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
            'tour_id' => ['required', 'integer', 'exists:tours,tour_id'],
            'departure_date' => $this->isMethod('post') ? ['required', 'date_format:Y-m-d', 'after_or_equal:today'] : ['required', 'date_format:Y-m-d'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
            'max_seats' => ['required', 'integer', 'min:1', 'max:200'],
            'available_seats' => ['required', 'integer', 'min:0', 'lte:max_seats'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'guide_id' => ['nullable', 'integer', 'exists:employees,employee_id'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['open', 'full', 'cancelled'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['tour_id', 'departure_date', 'return_date'])) {
                return;
            }

            if ($this->filled('guide_id') && ! $validator->errors()->has('guide_id')) {
                $guide = Employee::with('role')->find($this->integer('guide_id'));
                if (! $guide || ! $guide->is_active || $guide->role->role_name !== 'Guide') {
                    $validator->errors()->add('guide_id', 'Hãy chọn hướng dẫn viên đang hoạt động.');
                } elseif ($this->input('status') !== 'cancelled') {
                    $current = $this->route('departure');
                    $overlap = TourDeparture::where('guide_id', $guide->getKey())->where('status', '!=', 'cancelled')
                        ->when($current instanceof TourDeparture, fn ($q) => $q->whereKeyNot($current->getKey()))
                        ->whereDate('departure_date', '<=', $this->input('return_date'))
                        ->whereDate('return_date', '>=', $this->input('departure_date'))->exists();
                    if ($overlap) {
                        $validator->errors()->add('guide_id', 'Hướng dẫn viên đã có chuyến trong khoảng ngày này.');
                    }
                }
            }

            $tour = Tour::find($this->integer('tour_id'));
            if ($tour) {
                $departure = Carbon::parse($this->input('departure_date'));
                $return = Carbon::parse($this->input('return_date'));
                $days = (int) $departure->diffInDays($return) + 1;
                if ($days !== (int) $tour->duration_days) {
                    $validator->errors()->add('return_date', "Số ngày đi thực tế ({$days} ngày) phải khớp với thời lượng của tour ({$tour->duration_days} ngày).");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'departure_date.after_or_equal' => 'Ngày khởi hành phải từ ngày hôm nay trở đi.',
            'available_seats.lte' => 'Số chỗ còn lại không được lớn hơn tổng số chỗ.',
            'tour_id.required' => 'Vui lòng chọn tour.',
            'departure_time.date_format' => 'Giờ khởi hành không hợp lệ.',
            'departure_date.date_format' => 'Ngày khởi hành không hợp lệ, hãy nhập dạng dd/mm/yyyy.',
            'departure_date.required' => 'Vui lòng nhập ngày khởi hành.',
            'return_date.date_format' => 'Ngày về không hợp lệ, hãy nhập dạng dd/mm/yyyy.',
            'return_date.required' => 'Vui lòng nhập ngày về.',
            'return_date.after_or_equal' => 'Ngày về phải sau hoặc bằng ngày khởi hành.',
            'adult_price.required' => 'Vui lòng nhập giá người lớn.',
            'adult_price.numeric' => 'Giá người lớn không hợp lệ.',
        ];
    }
}
