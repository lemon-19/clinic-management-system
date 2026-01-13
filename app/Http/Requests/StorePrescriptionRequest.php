<?php
// ============================================
// app/Http/Requests/StorePrescriptionRequest.php
// ============================================

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_record_id' => 'required|exists:medical_records,id',
            'prescribed_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'is_visible_to_patient' => 'boolean',
            'medications' => 'required|array|min:1',
            'medications.*.medication_name' => 'required|string|max:255',
            'medications.*.dosage' => 'nullable|string|max:100',
            'medications.*.frequency' => 'nullable|string|max:100',
            'medications.*.duration' => 'nullable|string|max:100',
            'medications.*.instructions' => 'nullable|string|max:500',
            'medications.*.quantity' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'medical_record_id.required' => 'Medical record is required',
            'medical_record_id.exists' => 'Medical record does not exist',
            'prescribed_date.required' => 'Prescription date is required',
            'prescribed_date.before_or_equal' => 'Prescription date cannot be in the future',
            'medications.required' => 'At least one medication is required',
            'medications.*.medication_name.required' => 'Medication name is required for each medication',
        ];
    }
}
?>