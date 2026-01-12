<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorScheduleRequest extends FormRequest
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
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration' => ['nullable', 'integer', 'min:15', 'max:480'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'doctor_id.required' => 'Doctor is required',
            'doctor_id.exists' => 'Invalid doctor selected',
            'clinic_id.required' => 'Clinic is required',
            'clinic_id.exists' => 'Invalid clinic selected',
            'day_of_week.required' => 'Day of week is required',
            'day_of_week.between' => 'Day of week must be between 0 (Sunday) and 6 (Saturday)',
            'start_time.required' => 'Start time is required',
            'start_time.date_format' => 'Start time must be in HH:mm format',
            'end_time.required' => 'End time is required',
            'end_time.date_format' => 'End time must be in HH:mm format',
            'end_time.after' => 'End time must be after start time',
            'slot_duration.min' => 'Slot duration must be at least 15 minutes',
            'slot_duration.max' => 'Slot duration cannot exceed 480 minutes (8 hours)',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default slot duration if not provided
        if (!$this->has('slot_duration')) {
            $this->merge(['slot_duration' => 30]);
        }

        // Set default is_available to true if not provided
        if (!$this->has('is_available')) {
            $this->merge(['is_available' => true]);
        }
    }
}