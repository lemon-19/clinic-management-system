<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkRescheduleConflictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'exists:doctors,id'],
            'clinic_id' => ['required', 'exists:clinics,id'],
            'date_from' => ['required', 'date', 'after:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'new_date' => ['required', 'date', 'after:today'],
            'new_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'doctor_id.required' => 'Doctor ID is required',
            'clinic_id.required' => 'Clinic ID is required',
            'date_from.required' => 'Start date is required',
            'date_to.required' => 'End date is required',
            'new_date.required' => 'New appointment date is required',
            'new_time.required' => 'New appointment time is required',
        ];
    }
}
