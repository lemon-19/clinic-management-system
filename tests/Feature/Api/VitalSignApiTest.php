<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\VitalSign;
use App\Enums\UserType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalSignApiTest extends TestCase
{
    use RefreshDatabase;

    private $apiPrefix = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPermissions();
    }

    private function createPermissions(): void
    {
        $permissions = [
            'view_vital_signs',
            'create_vital_signs',
            'update_vital_signs',
            'delete_vital_signs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctorRole->syncPermissions([
            'view_vital_signs',
            'create_vital_signs',
            'update_vital_signs',
        ]);
    }

    private function createAuthenticatedDoctor(): array
    {
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctorUser->assignRole('doctor');
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        return ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token];
    }

    private function createAuthenticatedAdmin(): array
    {
        $adminUser = User::factory()->create(['user_type' => UserType::ADMIN]);
        $adminUser->assignRole('admin');
        $token = $adminUser->createToken('test-token')->plainTextToken;

        return ['user' => $adminUser, 'token' => $token];
    }

    /**
     * ============================================
     * RECORD VITAL SIGNS TESTS
     * ============================================
     */

    public function test_doctor_can_record_vital_signs(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.50,
                'height' => 180.00,
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 72,
                'temperature' => 37.0,
                'respiratory_rate' => 16,
                'oxygen_saturation' => 98,
                'blood_sugar' => 110.5,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'medical_record_id',
                'recorded_by',
                'measurements',
                'health_indicators',
                'recorded_at',
            ],
        ]);

        $this->assertDatabaseHas('vital_signs', [
            'medical_record_id' => $medicalRecord->id,
            'weight' => 75.50,
        ]);
    }

    public function test_bmi_calculated_automatically(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.00,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        // Compare as strings since DB returns decimal as string
        $this->assertEquals('23.15', $response->json('data.measurements.bmi'));
        $response->assertJsonPath('data.measurements.bmi_category', 'Normal weight');
    }

    public function test_bmi_category_underweight(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 45.00,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.measurements.bmi_category', 'Underweight');
    }

    public function test_bmi_category_overweight(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 95.00,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.measurements.bmi_category', 'Overweight');
    }

    public function test_bmi_category_obese(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 120.00,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.measurements.bmi_category', 'Obese');
    }

    public function test_high_blood_pressure_detected(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'blood_pressure_systolic' => 150,
                'blood_pressure_diastolic' => 95,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.health_indicators.is_high_blood_pressure', true);
    }

    public function test_normal_blood_pressure(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.health_indicators.is_high_blood_pressure', false);
    }

    public function test_abnormal_heart_rate_low(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'heart_rate' => 55,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.health_indicators.is_abnormal_heart_rate', true);
    }

    public function test_abnormal_heart_rate_high(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'heart_rate' => 110,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.health_indicators.is_abnormal_heart_rate', true);
    }

    public function test_normal_heart_rate(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'heart_rate' => 72,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.health_indicators.is_abnormal_heart_rate', false);
    }

    public function test_partial_vital_signs_recorded(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.50,
                'heart_rate' => 72,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vital_signs', [
            'medical_record_id' => $medicalRecord->id,
            'weight' => 75.50,
            'heart_rate' => 72,
        ]);
    }

    /**
     * ============================================
     * LIST VITAL SIGNS TESTS
     * ============================================
     */

    public function test_can_list_vital_signs_by_medical_record(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        VitalSign::factory()->count(5)->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'medical_record_id',
                    'recorded_by',
                    'measurements',
                    'health_indicators',
                ]
            ],
            'pagination' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
            ],
        ]);

        $this->assertEquals(5, $response->json('pagination.total'));
    }

    public function test_can_list_vital_signs_by_patient(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $clinic = Clinic::factory()->create();

        $medicalRecord1 = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $medicalRecord2 = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        VitalSign::factory()->count(3)->create([
            'medical_record_id' => $medicalRecord1->id,
            'recorded_by' => $doctorUser->id,
        ]);

        VitalSign::factory()->count(2)->create([
            'medical_record_id' => $medicalRecord2->id,
            'recorded_by' => $doctorUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/patients/{$patient->id}/vital-signs");

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('pagination.total'));
    }

    public function test_vital_signs_pagination(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        VitalSign::factory()->count(25)->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs?per_page=10");

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(10, $response->json('pagination.per_page'));
    }

    public function test_vital_signs_date_range_filter(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        // Create vital signs on different dates
        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'recorded_at' => now()->subDays(10), // Outside range
        ]);

        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'recorded_at' => now()->subDays(5), // Inside range
        ]);

        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'recorded_at' => now()->subDays(2), // Inside range
        ]);

        // Query for records within last 7 days
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

     /**
     * ============================================
     * GET SINGLE VITAL SIGN TESTS
     * ============================================
     */

    public function test_can_get_single_vital_sign(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $vitalSign = VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'weight' => 75.50,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/vital-signs/{$vitalSign->id}");

        $response->assertStatus(200);
        // Compare as string since decimal columns return strings in JSON
        $this->assertEquals('75.50', $response->json('data.measurements.weight'));
        $response->assertJsonPath('data.id', $vitalSign->id);
    }

    public function test_cannot_get_nonexistent_vital_sign(): void
    {
        ['token' => $token] = $this->createAuthenticatedDoctor();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . '/vital-signs/99999');

        $response->assertStatus(404);
    }

    /**
     * ============================================
     * UPDATE VITAL SIGNS TESTS
     * ============================================
     */

    public function test_can_update_vital_signs(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $vitalSign = VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'heart_rate' => 72,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson($this->apiPrefix . "/vital-signs/{$vitalSign->id}", [
                'heart_rate' => 80,
                'temperature' => 36.8,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.measurements.heart_rate', 80);
        // Compare as string
        $this->assertEquals('36.8', $response->json('data.measurements.temperature'));

        $this->assertDatabaseHas('vital_signs', [
            'id' => $vitalSign->id,
            'heart_rate' => 80,
        ]);
    }

    public function test_bmi_recalculated_on_update(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $vitalSign = VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'weight' => 75.00,
            'height' => 180.00,
            'bmi' => 23.15,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson($this->apiPrefix . "/vital-signs/{$vitalSign->id}", [
                'weight' => 90.00,
            ]);

        $response->assertStatus(200);
        // Compare as string
        $this->assertEquals('27.78', $response->json('data.measurements.bmi'));
        $response->assertJsonPath('data.measurements.bmi_category', 'Overweight');
    }


    /**
     * ============================================
     * DELETE VITAL SIGNS TESTS
     * ============================================
     */

    public function test_admin_can_delete_vital_signs(): void
    {
        ['user' => $adminUser, 'token' => $token] = $this->createAuthenticatedAdmin();

        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $vitalSign = VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson($this->apiPrefix . "/vital-signs/{$vitalSign->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vital_signs', ['id' => $vitalSign->id]);
    }

    public function test_doctor_cannot_delete_vital_signs(): void
    {
        ['user' => $doctorUser, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'clinic_id' => $clinic->id,
        ]);

        $vitalSign = VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson($this->apiPrefix . "/vital-signs/{$vitalSign->id}");

        $response->assertStatus(403);
    }

    /**
     * ============================================
     * TRENDS TESTS
     * ============================================
     */

    public function test_can_get_vital_signs_trends(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'weight' => 75.00,
            'height' => 180.00,
            'recorded_at' => now()->subDays(2),
        ]);

        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'weight' => 76.00,
            'height' => 180.00,
            'recorded_at' => now()->subDays(1),
        ]);

        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'weight' => 77.00,
            'height' => 180.00,
            'recorded_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs/trends");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'weight',
                'blood_pressure',
                'heart_rate',
                'temperature',
                'oxygen_saturation',
                'blood_sugar',
            ],
            'record_count',
        ]);

        $weight = $response->json('data.weight');
        $this->assertCount(3, $weight);
    }

    public function test_trends_with_date_range(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        // Old record outside date range (10 days ago)
        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'recorded_at' => now()->subDays(10)->startOfDay(), // Definitely outside
        ]);

        // First record within range (4 days ago)
        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'blood_pressure_systolic' => 125,
            'blood_pressure_diastolic' => 82,
            'recorded_at' => now()->subDays(4)->midDay(), // Inside range
        ]);

        // Second record within range (1 day ago)
        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'blood_pressure_systolic' => 130,
            'blood_pressure_diastolic' => 85,
            'recorded_at' => now()->subDays(1)->midDay(), // Inside range
        ]);

        // Query for last 7 days (should get 2 records)
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs/trends?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $bloodPressure = $response->json('data.blood_pressure');
        // Should have 2 blood pressure records within range
        $this->assertCount(2, $bloodPressure);
    }

    public function test_get_latest_vital_signs(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'heart_rate' => 70,
            'recorded_at' => now()->subDays(2),
        ]);

        $latestVitalSign = VitalSign::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'recorded_by' => $doctorUser->id,
            'heart_rate' => 80,
            'recorded_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs/latest");

        $response->assertStatus(200);
        $response->assertJsonPath('data.measurements.heart_rate', 80);
        $response->assertJsonPath('data.id', $latestVitalSign->id);
    }

    /**
     * ============================================
     * VALIDATION TESTS
     * ============================================
     */

    public function test_medical_record_id_required(): void
    {
        ['token' => $token] = $this->createAuthenticatedDoctor();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'weight' => 75.50,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['medical_record_id']);
    }

    public function test_invalid_medical_record_id(): void
    {
        ['token' => $token] = $this->createAuthenticatedDoctor();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => 99999,
                'weight' => 75.50,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['medical_record_id']);
    }

    public function test_recorded_at_required(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.50,
                'height' => 180.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recorded_at']);
    }

    public function test_recorded_at_cannot_be_future(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.50,
                'height' => 180.00,
                'recorded_at' => now()->addDays(1)->toDateTimeString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recorded_at']);
    }

    public function test_weight_must_be_numeric(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 'invalid',
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['weight']);
    }

    public function test_temperature_range_validation(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'temperature' => 100,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['temperature']);
    }

    /**
     * ============================================
     * AUTHORIZATION TESTS
     * ============================================
     */

    public function test_unauthenticated_user_cannot_create_vital_signs(): void
    {
        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->postJson($this->apiPrefix . '/vital-signs', [
            'medical_record_id' => $medicalRecord->id,
            'weight' => 75.50,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(401);
    }

    public function test_patient_cannot_create_vital_signs(): void
    {
        $patientUser = User::factory()->create(['user_type' => UserType::PATIENT]);
        $token = $patientUser->createToken('test-token')->plainTextToken;

        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patientUser->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.50,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(403);
    }

    /**
     * ============================================
     * EDGE CASES
     * ============================================
     */

    public function test_no_vital_signs_for_medical_record(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    public function test_latest_vital_signs_no_records(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/vital-signs/latest");

        $response->assertStatus(404);
    }

    public function test_recorded_by_is_authenticated_user(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/vital-signs', [
                'medical_record_id' => $medicalRecord->id,
                'weight' => 75.50,
                'height' => 180.00,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.recorded_by.id', $doctorUser->id);
        $response->assertJsonPath('data.recorded_by.name', $doctorUser->name);
    }
}