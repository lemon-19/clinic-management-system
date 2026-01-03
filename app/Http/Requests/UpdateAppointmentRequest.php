<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'patient_id' => ['nullable', 'exists:users,id'],
            'appointment_type' => ['nullable', Rule::in(['in_person','telemedicine'])],
            'status' => ['nullable', 'string', 'max:50'],
            'reason' => ['nullable', 'string'],
            'patient_notes' => ['nullable', 'string'],
            'doctor_notes' => ['nullable', 'string'],
            'appointment_date' => ['nullable', 'date'],
            'appointment_time' => ['nullable', 'date'],
        ];
    }
}
