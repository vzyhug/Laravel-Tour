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
        ];
    }
}
