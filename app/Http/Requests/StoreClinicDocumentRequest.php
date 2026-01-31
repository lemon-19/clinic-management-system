<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is validated in controller (owner/staff or admin)
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['proof_of_address', 'business_registration', 'dti_permit', 'owner_valid_id'])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // max 5MB
        ];
    }
}
