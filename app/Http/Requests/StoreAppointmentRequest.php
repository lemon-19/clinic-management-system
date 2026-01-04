<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            if (empty($data['doctor_id']) || empty($data['clinic_id']) || empty($data['appointment_time'])) {
                return;
            }

            $appointmentStart = Carbon::parse($data['appointment_time']);
            [$ok, $message] = \App\Models\Appointment::isSlotAvailable($data['doctor_id'], $data['clinic_id'], $appointmentStart);

            if (! $ok) {
                \Illuminate\Support\Facades\Log::info('Slot availability check failed in request', ['doctor_id' => $data['doctor_id'], 'clinic_id' => $data['clinic_id'], 'appointment_time' => $appointmentStart->toDateTimeString(), 'message' => $message]);
                $validator->errors()->add('appointment_time', $message);
            }
        });
    }
}
