<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDoctorSchedulesRequest extends FormRequest
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
            'schedules' => ['required', 'array', 'min:1', 'max:7'],
            'schedules.*.day_of_week' => ['required', 'integer', 'distinct', 'between:0,6'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.slot_duration' => ['nullable', 'integer', 'min:15', 'max:480'],
            'schedules.*.is_available' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $schedules = $this->input('schedules', []);

            foreach ($schedules as $index => $schedule) {
                if (isset($schedule['start_time']) && isset($schedule['end_time'])) {
                    if ($schedule['end_time'] <= $schedule['start_time']) {
                        $validator->errors()->add(
                            "schedules.{$index}.end_time",
                            'End time must be after start time'
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'doctor_id.required' => 'Doctor ID is required',
            'doctor_id.exists' => 'Invalid doctor selected',
            'clinic_id.required' => 'Clinic ID is required',
            'clinic_id.exists' => 'Invalid clinic selected',
            'schedules.required' => 'At least one schedule is required',
            'schedules.max' => 'Cannot update more than 7 schedules at once',
            'schedules.*.day_of_week.required' => 'Day of week is required for each schedule',
            'schedules.*.day_of_week.distinct' => 'Each day can only appear once',
            'schedules.*.day_of_week.between' => 'Day of week must be between 0 and 6',
            'schedules.*.start_time.required' => 'Start time is required for each schedule',
            'schedules.*.start_time.date_format' => 'Start time must be in HH:mm format',
            'schedules.*.end_time.required' => 'End time is required for each schedule',
            'schedules.*.end_time.date_format' => 'End time must be in HH:mm format',
            'schedules.*.slot_duration.min' => 'Slot duration must be at least 15 minutes',
            'schedules.*.slot_duration.max' => 'Slot duration cannot exceed 480 minutes',
        ];
    }

    protected function prepareForValidation(): void
    {
        $schedules = $this->input('schedules', []);

        foreach ($schedules as &$schedule) {
            if (!isset($schedule['slot_duration'])) {
                $schedule['slot_duration'] = 30;
            }
            if (!isset($schedule['is_available'])) {
                $schedule['is_available'] = true;
            }
        }

        $this->merge(['schedules' => $schedules]);
    }
}