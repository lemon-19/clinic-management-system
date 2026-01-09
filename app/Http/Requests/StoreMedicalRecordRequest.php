<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only doctors and admins can create medical records
        return $this->user()->user_type?->value === 'doctor' || 
               $this->user()->user_type?->value === 'admin';
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:users,id'],
            'clinic_id' => ['required', 'exists:clinics,id'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'visit_date' => ['required', 'date', 'before_or_equal:today'],
            'chief_complaint' => ['nullable', 'string', 'max:500'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'treatment_plan' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_visible_to_patient' => ['nullable', 'boolean'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:100'],
            'medications' => ['nullable', 'array'],
            'medications.*' => ['string', 'max:100'],
            'medical_history' => ['nullable', 'array'],
            'medical_history.*' => ['string', 'max:100'],
            'family_history' => ['nullable', 'array'],
            'family_history.*' => ['string', 'max:100'],
            'social_history' => ['nullable', 'array'],
            'social_history.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required',
            'clinic_id.required' => 'Clinic is required',
            'doctor_id.required' => 'Doctor is required',
            'visit_date.required' => 'Visit date is required',
            'visit_date.before_or_equal' => 'Visit date cannot be in the future',
        ];
    }
}