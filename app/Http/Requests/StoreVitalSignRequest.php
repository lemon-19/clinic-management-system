<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_record_id' => 'required|exists:medical_records,id',
            'weight' => 'nullable|numeric|min:0.01|max:999.99',
            'height' => 'nullable|numeric|min:0.01|max:999.99',
            'blood_pressure_systolic' => 'nullable|integer|min:0|max:300',
            'blood_pressure_diastolic' => 'nullable|integer|min:0|max:300',
            'heart_rate' => 'nullable|integer|min:0|max:300',
            'temperature' => 'nullable|numeric|min:20|max:45',
            'respiratory_rate' => 'nullable|integer|min:0|max:100',
            'oxygen_saturation' => 'nullable|integer|min:0|max:100',
            'blood_sugar' => 'nullable|numeric|min:0|max:999.99',
            'recorded_at' => 'required|date_format:Y-m-d H:i:s|before_or_equal:now',
        ];
    }

    public function messages(): array
    {
        return [
            'medical_record_id.required' => 'Medical record is required',
            'medical_record_id.exists' => 'Medical record does not exist',
            'weight.numeric' => 'Weight must be a valid number',
            'height.numeric' => 'Height must be a valid number',
            'recorded_at.before_or_equal' => 'Recorded time cannot be in the future',
        ];
    }
}