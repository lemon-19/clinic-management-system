<?php

namespace App\Http\Controllers\Api;

use App\Models\VitalSign;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use App\Http\Requests\StoreVitalSignRequest;
use App\Http\Requests\UpdateVitalSignRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VitalSignController extends Controller
{
    use AuthorizesRequests;

    /**
     * Record vital signs for a medical record
     * POST /api/v1/vital-signs
     */
    public function store(StoreVitalSignRequest $request): JsonResponse
    {
        $medicalRecord = MedicalRecord::findOrFail($request->medical_record_id);

        // Check authorization
        $this->authorize('create', VitalSign::class);

        // Calculate BMI if weight and height are provided
        $bmi = null;
        if ($request->weight && $request->height) {
            $bmi = VitalSign::calculateBMI($request->weight, $request->height);
        }

        $vitalSign = VitalSign::create([
            'medical_record_id' => $request->medical_record_id,
            'recorded_by' => auth()->id(),
            'weight' => $request->weight,
            'height' => $request->height,
            'blood_pressure_systolic' => $request->blood_pressure_systolic,
            'blood_pressure_diastolic' => $request->blood_pressure_diastolic,
            'heart_rate' => $request->heart_rate,
            'temperature' => $request->temperature,
            'respiratory_rate' => $request->respiratory_rate,
            'oxygen_saturation' => $request->oxygen_saturation,
            'blood_sugar' => $request->blood_sugar,
            'bmi' => $bmi,
            'recorded_at' => $request->recorded_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vital signs recorded successfully',
            'data' => $this->formatVitalSignResponse($vitalSign),
        ], 201);
    }

    /**
     * Get vital signs for a specific medical record
     * GET /api/v1/medical-records/{recordId}/vital-signs
     */
    public function indexByRecord($recordId, Request $request): JsonResponse
    {
        // Check authorization
        $this->authorize('viewAny', VitalSign::class);

        $medicalRecord = MedicalRecord::findOrFail($recordId);

        $query = $medicalRecord->vitalSigns();

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $vitalSigns = $query->latestFirst()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $vitalSigns->map(fn($vs) => $this->formatVitalSignResponse($vs)),
            'pagination' => [
                'total' => $vitalSigns->total(),
                'per_page' => $vitalSigns->perPage(),
                'current_page' => $vitalSigns->currentPage(),
                'last_page' => $vitalSigns->lastPage(),
            ],
        ]);
    }

    /**
     * Get vital signs for a specific patient
     * GET /api/v1/patients/{patientId}/vital-signs
     */
    public function indexByPatient($patientId, Request $request): JsonResponse
    {
        // Check authorization
        $this->authorize('viewAny', VitalSign::class);

        $query = VitalSign::whereHas('medicalRecord', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        });

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $vitalSigns = $query->latestFirst()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $vitalSigns->map(fn($vs) => $this->formatVitalSignResponse($vs)),
            'pagination' => [
                'total' => $vitalSigns->total(),
                'per_page' => $vitalSigns->perPage(),
                'current_page' => $vitalSigns->currentPage(),
                'last_page' => $vitalSigns->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single vital sign record
     * GET /api/v1/vital-signs/{id}
     */
    public function show($id): JsonResponse
    {
        $vitalSign = VitalSign::findOrFail($id);

        $this->authorize('view', $vitalSign);

        return response()->json([
            'success' => true,
            'data' => $this->formatVitalSignResponse($vitalSign),
        ]);
    }

    /**
     * Update vital signs
     * PUT /api/v1/vital-signs/{id}
     */
    public function update($id, UpdateVitalSignRequest $request): JsonResponse
    {
        $vitalSign = VitalSign::findOrFail($id);

        $this->authorize('update', $vitalSign);

        // Recalculate BMI if weight or height changed
        $bmi = $vitalSign->bmi;
        $weight = $request->weight ?? $vitalSign->weight;
        $height = $request->height ?? $vitalSign->height;

        if ($weight && $height) {
            $bmi = VitalSign::calculateBMI($weight, $height);
        }

        $vitalSign->update([
            'weight' => $request->weight ?? $vitalSign->weight,
            'height' => $request->height ?? $vitalSign->height,
            'blood_pressure_systolic' => $request->blood_pressure_systolic ?? $vitalSign->blood_pressure_systolic,
            'blood_pressure_diastolic' => $request->blood_pressure_diastolic ?? $vitalSign->blood_pressure_diastolic,
            'heart_rate' => $request->heart_rate ?? $vitalSign->heart_rate,
            'temperature' => $request->temperature ?? $vitalSign->temperature,
            'respiratory_rate' => $request->respiratory_rate ?? $vitalSign->respiratory_rate,
            'oxygen_saturation' => $request->oxygen_saturation ?? $vitalSign->oxygen_saturation,
            'blood_sugar' => $request->blood_sugar ?? $vitalSign->blood_sugar,
            'bmi' => $bmi,
            'recorded_at' => $request->recorded_at ?? $vitalSign->recorded_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vital signs updated successfully',
            'data' => $this->formatVitalSignResponse($vitalSign),
        ]);
    }

    /**
     * Delete vital signs
     * DELETE /api/v1/vital-signs/{id}
     */
    public function destroy($id): JsonResponse
    {
        $vitalSign = VitalSign::findOrFail($id);

        $this->authorize('delete', $vitalSign);

        $vitalSign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vital signs deleted successfully',
        ]);
    }

    /**
     * Get vital signs trends for a medical record
     * GET /api/v1/medical-records/{recordId}/vital-signs/trends
     */
    public function getTrends($recordId, Request $request): JsonResponse
    {
        $this->authorize('viewAny', VitalSign::class);

        $medicalRecord = MedicalRecord::findOrFail($recordId);

        $query = $medicalRecord->vitalSigns();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $vitalSigns = $query->latestFirst()->get();

        // Group data by measurement type
        $trends = [
            'weight' => $vitalSigns->filter(fn($vs) => $vs->weight)
                ->map(fn($vs) => [
                    'date' => $vs->recorded_at->format('Y-m-d'),
                    'value' => $vs->weight,
                    'bmi' => $vs->bmi,
                    'bmi_category' => $vs->getBMICategory(),
                ])
                ->values(),
            'blood_pressure' => $vitalSigns->filter(fn($vs) => $vs->blood_pressure_systolic || $vs->blood_pressure_diastolic)
                ->map(fn($vs) => [
                    'date' => $vs->recorded_at->format('Y-m-d'),
                    'systolic' => $vs->blood_pressure_systolic,
                    'diastolic' => $vs->blood_pressure_diastolic,
                    'is_high' => $vs->isHighBloodPressure(),
                ])
                ->values(),
            'heart_rate' => $vitalSigns->filter(fn($vs) => $vs->heart_rate)
                ->map(fn($vs) => [
                    'date' => $vs->recorded_at->format('Y-m-d'),
                    'value' => $vs->heart_rate,
                    'is_abnormal' => $vs->isAbnormalHeartRate(),
                ])
                ->values(),
            'temperature' => $vitalSigns->filter(fn($vs) => $vs->temperature)
                ->map(fn($vs) => [
                    'date' => $vs->recorded_at->format('Y-m-d'),
                    'value' => $vs->temperature,
                ])
                ->values(),
            'oxygen_saturation' => $vitalSigns->filter(fn($vs) => $vs->oxygen_saturation)
                ->map(fn($vs) => [
                    'date' => $vs->recorded_at->format('Y-m-d'),
                    'value' => $vs->oxygen_saturation,
                ])
                ->values(),
            'blood_sugar' => $vitalSigns->filter(fn($vs) => $vs->blood_sugar)
                ->map(fn($vs) => [
                    'date' => $vs->recorded_at->format('Y-m-d'),
                    'value' => $vs->blood_sugar,
                ])
                ->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $trends,
            'record_count' => count($vitalSigns),
        ]);
    }

    /**
     * Get latest vital signs for a medical record
     * GET /api/v1/medical-records/{recordId}/vital-signs/latest
     */
    public function getLatest($recordId): JsonResponse
    {
        $this->authorize('viewAny', VitalSign::class);

        $medicalRecord = MedicalRecord::findOrFail($recordId);

        $vitalSign = $medicalRecord->vitalSigns()->latest('recorded_at')->first();

        if (!$vitalSign) {
            return response()->json([
                'success' => false,
                'message' => 'No vital signs found for this medical record',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVitalSignResponse($vitalSign),
        ]);
    }

    /**
     * Format vital sign response
     */
    private function formatVitalSignResponse(VitalSign $vitalSign): array
    {
        return [
            'id' => $vitalSign->id,
            'medical_record_id' => $vitalSign->medical_record_id,
            'recorded_by' => $vitalSign->recordedBy ? [
                'id' => $vitalSign->recordedBy->id,
                'name' => $vitalSign->recordedBy->name,
            ] : null,
            'measurements' => [
                'weight' => $vitalSign->weight,
                'height' => $vitalSign->height,
                'bmi' => $vitalSign->bmi,
                'bmi_category' => $vitalSign->getBMICategory(),
                'blood_pressure' => [
                    'systolic' => $vitalSign->blood_pressure_systolic,
                    'diastolic' => $vitalSign->blood_pressure_diastolic,
                ],
                'heart_rate' => $vitalSign->heart_rate,
                'temperature' => $vitalSign->temperature,
                'respiratory_rate' => $vitalSign->respiratory_rate,
                'oxygen_saturation' => $vitalSign->oxygen_saturation,
                'blood_sugar' => $vitalSign->blood_sugar,
            ],
            'health_indicators' => [
                'is_high_blood_pressure' => $vitalSign->isHighBloodPressure(),
                'is_abnormal_heart_rate' => $vitalSign->isAbnormalHeartRate(),
            ],
            'recorded_at' => $vitalSign->recorded_at?->format('Y-m-d H:i:s'),
            'created_at' => $vitalSign->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $vitalSign->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}