<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckScheduleConflictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'exists:doctor_schedules,id'],
            'doctor_id' => ['required_without:schedule_id', 'nullable', 'exists:doctors,id'],
            'clinic_id' => ['required_without:schedule_id', 'nullable', 'exists:clinics,id'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.exists' => 'Invalid schedule selected',
            'doctor_id.required_without' => 'Doctor ID is required if schedule_id is not provided',
            'doctor_id.exists' => 'Invalid doctor selected',
            'clinic_id.required_without' => 'Clinic ID is required if schedule_id is not provided',
            'clinic_id.exists' => 'Invalid clinic selected',
            'day_of_week.between' => 'Day of week must be between 0 and 6',
        ];
    }
}