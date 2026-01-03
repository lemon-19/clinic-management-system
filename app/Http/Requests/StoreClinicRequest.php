<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:255'],
            'clinic_type' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'address_id' => ['nullable', 'exists:addresses,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', 'unique:clinics,email'],
            'status' => ['nullable', Rule::in(['active','inactive','approved','pending'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
