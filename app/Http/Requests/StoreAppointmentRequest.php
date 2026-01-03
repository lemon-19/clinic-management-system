<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['required', 'exists:clinics,id'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'patient_id' => ['required', 'exists:users,id'],
            'appointment_type' => ['nullable', Rule::in(['in_person','telemedicine'])],
            'status' => ['nullable', 'string', 'max:50'],
            'reason' => ['nullable', 'string'],
            'patient_notes' => ['nullable', 'string'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date'],
        ];
    }
}
