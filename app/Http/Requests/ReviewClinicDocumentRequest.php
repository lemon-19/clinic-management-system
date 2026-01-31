<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewClinicDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin-only endpoint (enforced by route middleware)
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['required_if:status,rejected', 'string', 'max:1000'],
        ];
    }
}
