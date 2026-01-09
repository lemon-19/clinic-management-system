<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Models\MedicalRecord;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MedicalRecordController extends Controller
{
    /**
     * List all medical records for a patient
     */
    public function indexByPatient(Request $request, $patientId): JsonResponse
    {
        // Convert to int for proper comparison
        $patientIdInt = (int)$patientId;
        $userIdInt = (int)$request->user()->id;
        
        // Authorization: patient can view own records, doctor/admin can view all
        if ($userIdInt !== $patientIdInt && 
            !in_array($request->user()->user_type?->value, ['doctor', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $records = MedicalRecord::where('patient_id', $patientIdInt)
            ->with(['patient', 'clinic', 'doctor'])
            ->latest('visit_date')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
            ],
        ]);
    }

    /**
     * List medical records for a clinic
     */
    public function indexByClinic(Request $request, $clinicId): JsonResponse
    {
        $records = MedicalRecord::where('clinic_id', $clinicId)
            ->with(['patient', 'doctor'])
            ->latest('visit_date')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
            ],
        ]);
    }

    /**
     * Create medical record (typically from completed appointment)
     */
    public function store(StoreMedicalRecordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $record = MedicalRecord::create($data);

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($request->user())
                ->withProperties($record->toArray())
                ->log('created medical record');
        }

        return response()->json([
            'message' => 'Medical record created successfully',
            'data' => $record->load(['patient', 'clinic', 'doctor']),
        ], 201);
    }

    /**
     * Get specific medical record
     */
    public function show(Request $request, $recordId): JsonResponse
    {
        $record = MedicalRecord::where('id', $recordId)
            ->orWhere('uuid', $recordId)
            ->firstOrFail();

        // Authorization: patient can view own/visible records, doctor/admin can view all
        if ($request->user()->id !== $record->patient_id && 
            !in_array($request->user()->user_type?->value, ['doctor', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load relationships
        $record->load(['patient', 'clinic', 'doctor', 'vitalSigns', 'prescriptions', 'labResults', 'documents']);

        return response()->json([
            'data' => $record,
        ]);
    }

    /**
     * Update medical record
     */
    public function update(UpdateMedicalRecordRequest $request, $recordId): JsonResponse
    {
        $record = MedicalRecord::where('id', $recordId)
            ->orWhere('uuid', $recordId)
            ->firstOrFail();

        $record->update($request->validated());

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($request->user())
                ->withProperties($record->getChanges())
                ->log('updated medical record');
        }

        return response()->json([
            'message' => 'Medical record updated successfully',
            'data' => $record->load(['patient', 'clinic', 'doctor']),
        ]);
    }

    /**
     * Delete medical record (soft delete)
     */
    public function destroy(Request $request, $recordId): JsonResponse
    {
        $record = MedicalRecord::where('id', $recordId)
            ->orWhere('uuid', $recordId)
            ->firstOrFail();

        // Only doctor who created it or admin can delete
        if ($request->user()->user_type?->value !== 'admin' && 
            $request->user()->doctor?->id !== $record->doctor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $record->delete();

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($request->user())
                ->withProperties($record->toArray())
                ->log('deleted medical record');
        }

        return response()->json(['message' => 'Medical record deleted successfully'], 204);
    }

    /**
     * Restore soft-deleted medical record
     */
    public function restore(Request $request, $recordId): JsonResponse
    {
        $record = MedicalRecord::withTrashed()
            ->where('id', $recordId)
            ->orWhere('uuid', $recordId)
            ->firstOrFail();

        // Only admin can restore
        if ($request->user()->user_type?->value !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $record->restore();

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($request->user())
                ->log('restored medical record');
        }

        return response()->json([
            'message' => 'Medical record restored successfully',
            'data' => $record,
        ]);
    }

    /**
     * Control visibility of medical record to patient
     */
    public function toggleVisibility(Request $request, $recordId): JsonResponse
    {
        $record = MedicalRecord::where('id', $recordId)
            ->orWhere('uuid', $recordId)
            ->firstOrFail();

        // Only doctor who created it or admin can change visibility
        if ($request->user()->user_type?->value !== 'admin' && 
            $request->user()->doctor?->id !== $record->doctor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $record->update([
            'is_visible_to_patient' => !$record->is_visible_to_patient,
        ]);

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($request->user())
                ->withProperties(['is_visible_to_patient' => $record->is_visible_to_patient])
                ->log('toggled medical record visibility');
        }

        return response()->json([
            'message' => 'Visibility updated successfully',
            'data' => $record,
        ]);
    }

    /**
     * Auto-create medical record from completed appointment
     * Called when appointment status changes to COMPLETED
     */
    public static function createFromAppointment(Appointment $appointment): ?MedicalRecord
    {
        if ($appointment->status?->value !== 'completed') {
            return null;
        }

        // Check if record already exists for this appointment
        $existing = MedicalRecord::where('patient_id', $appointment->patient_id)
            ->where('doctor_id', $appointment->doctor_id)
            ->whereDate('visit_date', $appointment->appointment_date->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        $record = MedicalRecord::create([
            'patient_id' => $appointment->patient_id,
            'clinic_id' => $appointment->clinic_id,
            'doctor_id' => $appointment->doctor_id,
            'visit_date' => $appointment->appointment_date,
            'chief_complaint' => $appointment->reason,
            'is_visible_to_patient' => true,
        ]);

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->withProperties(['appointment_id' => $appointment->id])
                ->log('auto-created medical record from appointment');
        }

        return $record;
    }
}