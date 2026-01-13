<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\PrescriptionMedication;
use App\Enums\UserType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionApiTest extends TestCase
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
            'view_prescriptions',
            'create_prescriptions',
            'update_prescriptions',
            'delete_prescriptions',  // Make sure this is included
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctorRole->syncPermissions([
            'view_prescriptions',
            'create_prescriptions',
            'update_prescriptions',
            'delete_prescriptions',  // ADD THIS LINE to the doctor role
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

    /**
     * ============================================
     * CREATE PRESCRIPTION TESTS
     * ============================================
     */

    public function test_doctor_can_create_prescription(): void
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
            ->postJson($this->apiPrefix . '/prescriptions', [
                'medical_record_id' => $medicalRecord->id,
                'prescribed_date' => now()->toDateString(),
                'notes' => 'Take after meals',
                'is_visible_to_patient' => true,
                'medications' => [
                    [
                        'medication_name' => 'Paracetamol',
                        'dosage' => '500mg',
                        'frequency' => 'Twice daily',
                        'duration' => '5 days',
                        'instructions' => 'Take with water',
                        'quantity' => 2,
                    ],
                    [
                        'medication_name' => 'Ibuprofen',
                        'dosage' => '400mg',
                        'frequency' => 'Once daily',
                        'duration' => '7 days',
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.medications', function ($medications) {
            return count($medications) === 2;
        });

        $this->assertDatabaseHas('prescriptions', [
            'medical_record_id' => $medicalRecord->id,
        ]);

        $this->assertDatabaseHas('prescription_medications', [
            'medication_name' => 'Paracetamol',
        ]);
    }

    public function test_prescription_requires_at_least_one_medication(): void
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
            ->postJson($this->apiPrefix . '/prescriptions', [
                'medical_record_id' => $medicalRecord->id,
                'prescribed_date' => now()->toDateString(),
                'medications' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['medications']);
    }

    public function test_prescription_date_cannot_be_future(): void
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
            ->postJson($this->apiPrefix . '/prescriptions', [
                'medical_record_id' => $medicalRecord->id,
                'prescribed_date' => now()->addDays(5)->toDateString(),
                'medications' => [
                    ['medication_name' => 'Paracetamol'],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['prescribed_date']);
    }

    /**
     * ============================================
     * LIST PRESCRIPTION TESTS
     * ============================================
     */

    public function test_can_list_prescriptions_by_medical_record(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        Prescription::factory()->count(3)->create([
            'medical_record_id' => $medicalRecord->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/prescriptions");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    public function test_can_list_prescriptions_by_patient(): void
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

        Prescription::factory()->count(2)->create(['medical_record_id' => $medicalRecord1->id]);
        Prescription::factory()->count(3)->create(['medical_record_id' => $medicalRecord2->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/patients/{$patient->id}/prescriptions");

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('pagination.total'));
    }

    public function test_prescriptions_pagination(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        Prescription::factory()->count(25)->create(['medical_record_id' => $medicalRecord->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$medicalRecord->id}/prescriptions?per_page=10");

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(25, $response->json('pagination.total'));
    }

    /**
     * ============================================
     * VIEW PRESCRIPTION TESTS
     * ============================================
     */

    public function test_can_get_prescription_details(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create(['medical_record_id' => $medicalRecord->id]);
        PrescriptionMedication::factory()->count(2)->create(['prescription_id' => $prescription->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/prescriptions/{$prescription->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.medications', function ($meds) {
            return count($meds) === 2;
        });
    }

    /**
     * ============================================
     * UPDATE PRESCRIPTION TESTS
     * ============================================
     */

    public function test_can_update_prescription(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'notes' => 'Old notes',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson($this->apiPrefix . "/prescriptions/{$prescription->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'notes' => 'Updated notes',
        ]);
    }

    /**
     * ============================================
     * ADD/REMOVE MEDICATION TESTS
     * ============================================
     */

    public function test_can_add_medication_to_prescription(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create(['medical_record_id' => $medicalRecord->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . "/prescriptions/{$prescription->id}/medications", [
                'medication_name' => 'Amoxicillin',
                'dosage' => '250mg',
                'frequency' => 'Three times daily',
                'duration' => '7 days',
                'quantity' => 3,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('prescription_medications', [
            'prescription_id' => $prescription->id,
            'medication_name' => 'Amoxicillin',
        ]);
    }

    public function test_can_remove_medication_from_prescription(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create(['medical_record_id' => $medicalRecord->id]);
        $medication = PrescriptionMedication::factory()->create(['prescription_id' => $prescription->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson($this->apiPrefix . "/prescriptions/{$prescription->id}/medications/{$medication->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('prescription_medications', ['id' => $medication->id]);
    }

    /**
     * ============================================
     * VISIBILITY CONTROL TESTS
     * ============================================
     */

    public function test_can_toggle_visibility(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'is_visible_to_patient' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson($this->apiPrefix . "/prescriptions/{$prescription->id}/visibility");

        $response->assertStatus(200);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'is_visible_to_patient' => false,
        ]);
    }

    /**
     * ============================================
     * DELETE PRESCRIPTION TESTS
     * ============================================
     */

    public function test_can_delete_prescription(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create(['medical_record_id' => $medicalRecord->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson($this->apiPrefix . "/prescriptions/{$prescription->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('prescriptions', ['id' => $prescription->id]);
    }

    /**
     * ============================================
     * PRINT PRESCRIPTION TESTS
     * ============================================
     */

    public function test_can_print_prescription(): void
    {
        ['user' => $doctorUser, 'doctor' => $doctor, 'token' => $token] = $this->createAuthenticatedDoctor();

        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $prescription = Prescription::factory()->create(['medical_record_id' => $medicalRecord->id]);
        PrescriptionMedication::factory()->count(2)->create(['prescription_id' => $prescription->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/prescriptions/{$prescription->id}/print");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'prescription_id',
                'prescription_date',
                'patient_name',
                'doctor_name',
                'medications',
                'notes',
            ],
        ]);
    }

    /**
     * ============================================
     * AUTHORIZATION TESTS
     * ============================================
     */

    public function test_unauthenticated_user_cannot_create_prescription(): void
    {
        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $medicalRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->postJson($this->apiPrefix . '/prescriptions', [
            'medical_record_id' => $medicalRecord->id,
            'medications' => [['medication_name' => 'Test']],
        ]);

        $response->assertStatus(401);
    }

    public function test_patient_cannot_create_prescription(): void
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
            ->postJson($this->apiPrefix . '/prescriptions', [
                'medical_record_id' => $medicalRecord->id,
                'medications' => [['medication_name' => 'Test']],
            ]);

        $response->assertStatus(403);
    }
}