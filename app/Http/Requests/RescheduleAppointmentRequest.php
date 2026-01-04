<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_date' => ['required','date','after:today'],
            'appointment_time' => ['required','date','after:now'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // The route parameter is 'appointment', which may be a string ID or an Appointment model
            $appointmentParam = $this->route('appointment');
            
            if (! $appointmentParam) {
                return;
            }

            // If it's a string ID, look up the appointment
            if (is_string($appointmentParam) || is_numeric($appointmentParam)) {
                $appt = \App\Models\Appointment::where('id', $appointmentParam)->orWhere('uuid', $appointmentParam)->first();
            } else {
                $appt = $appointmentParam;
            }

            if (! $appt) {
                return;
            }

            $data = $this->all();
            if (empty($data['appointment_time'])) {
                return;
            }

            $appointmentStart = Carbon::parse($data['appointment_time']);
            [$ok, $message] = \App\Models\Appointment::isSlotAvailable($appt->doctor_id, $appt->clinic_id, $appointmentStart, $appt->id);

            if (! $ok) {
                $validator->errors()->add('appointment_time', $message);
            }
        });
    }
}
