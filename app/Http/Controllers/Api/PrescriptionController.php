<?php

namespace App\Http\Controllers\Api;

use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Models\MedicalRecord;
use App\Http\Resources\PrescriptionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\UpdatePrescriptionRequest;
use App\Http\Requests\AddPrescriptionMedicationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionController extends BaseController
{
    use AuthorizesRequests;

    /**
     * Create a new prescription for a medical record
     * POST /api/v1/prescriptions
     */
    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        $medicalRecord = MedicalRecord::findOrFail($request->medical_record_id);
        
        $this->authorize('create', Prescription::class);

        // Create prescription
        $prescription = Prescription::create([
            'medical_record_id' => $request->medical_record_id,
            'prescribed_date' => $request->prescribed_date,
            'notes' => $request->notes,
            'is_visible_to_patient' => $request->is_visible_to_patient ?? true,
        ]);

        // Add medications
        foreach ($request->medications as $med) {
            PrescriptionMedication::create([
                'prescription_id' => $prescription->id,
                'medication_name' => $med['medication_name'],
                'dosage' => $med['dosage'] ?? null,
                'frequency' => $med['frequency'] ?? null,
                'duration' => $med['duration'] ?? null,
                'instructions' => $med['instructions'] ?? null,
                'quantity' => $med['quantity'] ?? 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Prescription created successfully',
            'data' => $this->formatPrescriptionResponse($prescription),
        ], 201);
    }

    /**
     * List prescriptions for a specific patient
     * GET /api/v1/patients/{patientId}/prescriptions
     */
    public function indexByPatient($patientId, Request $request): JsonResponse
    {
        $this->authorize('viewAny', Prescription::class);

        $query = Prescription::whereHas('medicalRecord', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        });

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('prescribed_date', [$request->start_date, $request->end_date]);
        }

        $prescriptions = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $prescriptions->map(fn($p) => $this->formatPrescriptionResponse($p)),
            'pagination' => [
                'total' => $prescriptions->total(),
                'per_page' => $prescriptions->perPage(),
                'current_page' => $prescriptions->currentPage(),
                'last_page' => $prescriptions->lastPage(),
            ],
        ]);
    }

    /**
     * List prescriptions for a specific medical record
     * GET /api/v1/medical-records/{recordId}/prescriptions
     */
    public function indexByRecord($recordId, Request $request): JsonResponse
    {
        $this->authorize('viewAny', Prescription::class);

        $medicalRecord = MedicalRecord::findOrFail($recordId);

        $prescriptions = $medicalRecord->prescriptions()
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $prescriptions->map(fn($p) => $this->formatPrescriptionResponse($p)),
            'pagination' => [
                'total' => $prescriptions->total(),
                'per_page' => $prescriptions->perPage(),
                'current_page' => $prescriptions->currentPage(),
                'last_page' => $prescriptions->lastPage(),
            ],
        ]);
    }

    /**
     * Get prescription details
     * GET /api/v1/prescriptions/{id}
     */
    public function show($id): JsonResponse
    {
        $prescription = Prescription::findOrFail($id);

        $this->authorize('view', $prescription);

        return response()->json([
            'success' => true,
            'data' => $this->formatPrescriptionResponse($prescription),
        ]);
    }

    /**
     * Update prescription
     * PUT /api/v1/prescriptions/{id}
     */
    public function update($id, UpdatePrescriptionRequest $request): JsonResponse
    {
        $prescription = Prescription::findOrFail($id);

        $this->authorize('update', $prescription);

        $prescription->update([
            'prescribed_date' => $request->prescribed_date ?? $prescription->prescribed_date,
            'notes' => $request->notes ?? $prescription->notes,
            'is_visible_to_patient' => $request->is_visible_to_patient ?? $prescription->is_visible_to_patient,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prescription updated successfully',
            'data' => $this->formatPrescriptionResponse($prescription),
        ]);
    }

    /**
     * Add medication to prescription
     * POST /api/v1/prescriptions/{id}/medications
     */
    public function addMedication($id, AddPrescriptionMedicationRequest $request): JsonResponse
    {
        $prescription = Prescription::findOrFail($id);

        $this->authorize('update', $prescription);

        $medication = PrescriptionMedication::create([
            'prescription_id' => $prescription->id,
            'medication_name' => $request->medication_name,
            'dosage' => $request->dosage,
            'frequency' => $request->frequency,
            'duration' => $request->duration,
            'instructions' => $request->instructions,
            'quantity' => $request->quantity ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medication added successfully',
            'data' => $this->formatPrescriptionResponse($prescription),
        ], 201);
    }

    /**
     * Remove medication from prescription
     * DELETE /api/v1/prescriptions/{id}/medications/{medicationId}
     */
    public function removeMedication($id, $medicationId): JsonResponse
    {
        $prescription = Prescription::findOrFail($id);

        $this->authorize('update', $prescription);

        $medication = PrescriptionMedication::findOrFail($medicationId);

        if ($medication->prescription_id !== $prescription->id) {
            return response()->json([
                'success' => false,
                'message' => 'Medication does not belong to this prescription',
            ], 400);
        }

        $medication->delete();

        return response()->json([
            'success' => true,
            'message' => 'Medication removed successfully',
        ]);
    }

    /**
     * Toggle visibility to patient
     * PATCH /api/v1/prescriptions/{id}/visibility
     */
    public function toggleVisibility($id): JsonResponse
    {
        $prescription = Prescription::findOrFail($id);

        $this->authorize('update', $prescription);

        $prescription->update([
            'is_visible_to_patient' => !$prescription->is_visible_to_patient,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visibility updated successfully',
            'data' => [
                'id' => $prescription->id,
                'is_visible_to_patient' => $prescription->is_visible_to_patient,
            ],
        ]);
    }

    /**
     * Delete prescription
     * DELETE /api/v1/prescriptions/{id}
     */
    public function destroy($id): JsonResponse
    {
        $prescription = Prescription::findOrFail($id);

        $this->authorize('delete', $prescription);

        $prescription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prescription deleted successfully',
        ]);
    }

    /**
     * Print prescription (generate formatted output)
     * GET /api/v1/prescriptions/{id}/print
     */
    public function print($id): JsonResponse
    {
        $prescription = Prescription::with(['medicalRecord.patient', 'medicalRecord.doctor', 'medications'])->findOrFail($id);

        $this->authorize('view', $prescription);

        $patient = $prescription->medicalRecord->patient;
        $doctor = $prescription->medicalRecord->doctor;

        $printData = [
            'prescription_id' => $prescription->id,
            'prescription_date' => $prescription->prescribed_date->format('Y-m-d'),
            'patient_name' => $patient->name,
            'patient_email' => $patient->email,
            'doctor_name' => $doctor->user->name,
            'medications' => $prescription->medications->map(fn($m) => [
                'medication' => $m->medication_name,
                'dosage' => $m->dosage,
                'frequency' => $m->frequency,
                'duration' => $m->duration,
                'instructions' => $m->instructions,
                'quantity' => $m->quantity,
            ]),
            'notes' => $prescription->notes,
        ];

        return response()->json([
            'success' => true,
            'data' => $printData,
        ]);
    }

    /**
     * Format prescription response
     */
    private function formatPrescriptionResponse(Prescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'medical_record_id' => $prescription->medical_record_id,
            'prescribed_date' => $prescription->prescribed_date->format('Y-m-d'),
            'notes' => $prescription->notes,
            'is_visible_to_patient' => $prescription->is_visible_to_patient,
            'medications' => $prescription->medications->map(fn($m) => [
                'id' => $m->id,
                'medication_name' => $m->medication_name,
                'dosage' => $m->dosage,
                'frequency' => $m->frequency,
                'duration' => $m->duration,
                'instructions' => $m->instructions,
                'quantity' => $m->quantity,
            ]),
            'created_at' => $prescription->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $prescription->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}