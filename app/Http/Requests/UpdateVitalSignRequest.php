<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight' => 'nullable|numeric|min:0.01|max:999.99',
            'height' => 'nullable|numeric|min:0.01|max:999.99',
            'blood_pressure_systolic' => 'nullable|integer|min:0|max:300',
            'blood_pressure_diastolic' => 'nullable|integer|min:0|max:300',
            'heart_rate' => 'nullable|integer|min:0|max:300',
            'temperature' => 'nullable|numeric|min:20|max:45',
            'respiratory_rate' => 'nullable|integer|min:0|max:100',
            'oxygen_saturation' => 'nullable|integer|min:0|max:100',
            'blood_sugar' => 'nullable|numeric|min:0|max:999.99',
            'recorded_at' => 'nullable|date_format:Y-m-d H:i:s|before_or_equal:now',
        ];
    }
}