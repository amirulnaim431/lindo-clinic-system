<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name'  => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],

            'service_id' => ['required', 'exists:services,id'],
            'staff_id'   => ['nullable', 'exists:staff,id'],

            // We will submit as "Y-m-d H:i"
            'start_at' => ['required', 'date_format:Y-m-d H:i'],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_at.date_format' => 'Invalid date/time format.',
        ];
    }
}