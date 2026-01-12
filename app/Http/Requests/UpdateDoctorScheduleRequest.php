<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'clinic_id' => ['nullable', 'exists:clinics,id'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'slot_duration' => ['nullable', 'integer', 'min:15', 'max:480'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // If both start_time and end_time are provided, validate end_time is after start_time
            if (isset($data['start_time']) && isset($data['end_time'])) {
                if ($data['end_time'] <= $data['start_time']) {
                    $validator->errors()->add('end_time', 'End time must be after start time');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'doctor_id.exists' => 'Invalid doctor selected',
            'clinic_id.exists' => 'Invalid clinic selected',
            'day_of_week.between' => 'Day of week must be between 0 (Sunday) and 6 (Saturday)',
            'start_time.date_format' => 'Start time must be in HH:mm format',
            'end_time.date_format' => 'End time must be in HH:mm format',
            'slot_duration.min' => 'Slot duration must be at least 15 minutes',
            'slot_duration.max' => 'Slot duration cannot exceed 480 minutes (8 hours)',
        ];
    }
}