<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'checkout_key' => ['required', 'uuid'],
            'quote_token' => ['required', 'string', 'max:10000'],
            'expected_total' => ['required', 'integer', 'min:0'],
            'policy_accepted' => ['required', 'accepted'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:100'],
            'contact_phone' => ['required', 'regex:/^(0|\+84)[0-9]{9}$/'],
            'passengers' => ['required', 'array', 'min:1', 'max:20'],
            'passengers.*' => ['array'],
            'passengers.*.full_name' => ['required', 'string', 'max:100'],
            'passengers.*.passenger_type' => ['required', 'in:adult,child'],
            'passengers.*.id_card' => ['required_if:passengers.*.passenger_type,adult', 'nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z]{8,20}$/'],
            'passengers.*.date_of_birth' => ['required_if:passengers.*.passenger_type,child', 'nullable', 'date', 'before:today'],
            'pickup_type' => ['required', 'in:self,door'],
            'pickup_address' => ['nullable', 'required_if:pickup_type,door', 'string', 'max:255'],
            'pickup_note' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $passengers = $this->input('passengers', []);
            if (! is_array($passengers)) {
                return;
            }

            foreach ($passengers as $index => $passenger) {
                $field = "passengers.{$index}.date_of_birth";
                if (! is_array($passenger) || $validator->errors()->has($field)) {
                    continue;
                }

                if (($passenger['passenger_type'] ?? '') === 'child' && ! empty($passenger['date_of_birth'])) {
                    if (Carbon::parse($passenger['date_of_birth'])->age >= 12) {
                        $validator->errors()->add($field, 'Hành khách trẻ em phải dưới 12 tuổi.');
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
            'passengers.*.id_card.required_if' => 'CCCD hoặc Hộ chiếu là bắt buộc đối với hành khách người lớn.',
            'passengers.*.id_card.regex' => 'CCCD hoặc Hộ chiếu phải từ 8 đến 20 ký tự chữ và số.',
            'passengers.*.date_of_birth.required_if' => 'Ngày sinh là bắt buộc đối với hành khách trẻ em.',
            'passengers.*.date_of_birth.before' => 'Ngày sinh phải trước ngày hôm nay.',
            'pickup_address.required_if' => 'Vui lòng nhập địa chỉ đón.',
            'pickup_address.max' => 'Địa chỉ đón không được quá 255 ký tự.',
            'pickup_note.max' => 'Ghi chú không được quá 255 ký tự.',
            'pickup_type.required' => 'Vui lòng chọn cách tham gia chuyến đi.',
            'pickup_type.in' => 'Cách tham gia chuyến đi không hợp lệ.',
        ];
    }
}
