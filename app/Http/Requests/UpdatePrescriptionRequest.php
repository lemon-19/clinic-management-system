<?php
// ============================================
// app/Http/Requests/UpdatePrescriptionRequest.php
// ============================================

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prescribed_date' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'is_visible_to_patient' => 'boolean',
        ];
    }
}
?>