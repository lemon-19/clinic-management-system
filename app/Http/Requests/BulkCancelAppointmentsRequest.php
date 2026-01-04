<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCancelAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_ids' => ['required', 'array', 'min:1'],
            'appointment_ids.*' => ['integer', 'exists:appointments,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_ids.required' => 'At least one appointment ID is required',
            'appointment_ids.array' => 'Appointment IDs must be an array',
            'appointment_ids.min' => 'At least one appointment ID is required',
            'appointment_ids.*.exists' => 'One or more appointment IDs do not exist',
        ];
    }
}
