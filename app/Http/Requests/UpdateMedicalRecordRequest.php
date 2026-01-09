<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Get the medical record from the route parameter
        $record = $this->route('recordId'); // This is a string ID or UUID
        
        // If recordId is provided as string, we need to query the database
        if (is_string($record) || is_numeric($record)) {
            $record = \App\Models\MedicalRecord::where('id', $record)
                ->orWhere('uuid', $record)
                ->first();
        }

        // If record doesn't exist, deny access (will be caught by controller)
        if (!$record) {
            return false;
        }

        // Only the doctor who created it or admin can update
        return $this->user()->user_type?->value === 'admin' || 
               $this->user()->doctor?->id === $record->doctor_id;
    }

    public function rules(): array
    {
        return [
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
}