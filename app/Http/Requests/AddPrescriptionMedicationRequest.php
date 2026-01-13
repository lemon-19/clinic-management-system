<?php
// ============================================
// app/Http/Requests/AddPrescriptionMedicationRequest.php
// ============================================

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPrescriptionMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medication_name' => 'required|string|max:255',
            'dosage' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:100',
            'duration' => 'nullable|string|max:100',
            'instructions' => 'nullable|string|max:500',
            'quantity' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'medication_name.required' => 'Medication name is required',
        ];
    }
}
?>
