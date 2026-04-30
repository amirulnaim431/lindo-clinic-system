<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->ignore($customer?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'dob' => ['nullable', 'date'],
            'ic_passport' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:50'],
            'marital_status' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],

            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'allergies' => ['nullable', 'string', 'max:2000'],

            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],

            'membership_code' => ['nullable', 'string', 'max:100'],
            'membership_type' => ['nullable', 'string', 'max:100'],
            'current_package' => ['nullable', 'string', 'max:150'],
            'current_package_since' => ['nullable', 'date'],
            'membership_package_value' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'membership_balance' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->cleanString($this->input('full_name')),
            'phone' => $this->cleanString($this->input('phone')),
            'email' => $this->cleanString($this->input('email')),
            'ic_passport' => $this->cleanString($this->input('ic_passport')),
            'gender' => $this->cleanString($this->input('gender')),
            'marital_status' => $this->cleanString($this->input('marital_status')),
            'nationality' => $this->cleanString($this->input('nationality')),
            'occupation' => $this->cleanString($this->input('occupation')),
            'address' => $this->cleanMultilineString($this->input('address')),
            'allergies' => $this->cleanMultilineString($this->input('allergies')),
            'emergency_contact_name' => $this->cleanString($this->input('emergency_contact_name')),
            'emergency_contact_phone' => $this->cleanString($this->input('emergency_contact_phone')),
            'membership_code' => $this->cleanString($this->input('membership_code')),
            'membership_type' => $this->cleanString($this->input('membership_type')),
            'current_package' => $this->cleanString($this->input('current_package')),
            'notes' => $this->cleanMultilineString($this->input('notes')),
        ]);
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        $validated['membership_package_value_cents'] = $this->moneyToCents($validated['membership_package_value'] ?? null);
        $validated['membership_balance_cents'] = $this->moneyToCents($validated['membership_balance'] ?? null);
        unset($validated['membership_package_value'], $validated['membership_balance']);

        return $validated;
    }

    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function cleanMultilineString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : preg_replace("/\r\n|\r/", "\n", $value);
    }

    protected function moneyToCents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }
}
