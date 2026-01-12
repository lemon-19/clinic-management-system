<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Enums\UserType;
use App\Enums\AppointmentStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    private $apiPrefix = '/api/v1'; // Use your API prefix

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permission exists
        Permission::firstOrCreate([
            'name' => 'complete_appointment',
            'guard_name' => 'web',
        ]);

        // Ensure doctor role exists
        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctorRole->givePermissionTo('complete_appointment');
    }

    /**
     * Test: Create a medical record successfully
     */
    public function test_doctor_can_create_medical_record(): void
    {
        // 1. Ensure the permission exists
        Permission::firstOrCreate(['name' => 'create_medical_record', 'guard_name' => 'web']);

        // 2. Create doctor user and assign permission
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctorUser->givePermissionTo('create_medical_record');

        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // 3. Create clinic and patient
        $clinic = Clinic::factory()->create();
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);

        // 4. Make the API request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/medical-records', [
                'patient_id' => $patient->id,
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
                'chief_complaint' => 'Severe headache',
                'diagnosis' => 'Migraine',
                'treatment_plan' => 'Prescribe pain reliever and rest',
                'notes' => 'Patient to follow up in 1 week',
                'is_visible_to_patient' => true,
                'allergies' => ['penicillin', 'peanuts'],
                'medications' => ['aspirin 500mg'],
                'medical_history' => ['hypertension'],
                'family_history' => ['diabetes'],
                'social_history' => ['smoker'],
            ]);

        $response->assertStatus(201);
    }

    /**
     * Test: Patient cannot create medical records
     */
    public function test_patient_cannot_create_medical_record(): void
    {
        // Ensure permission exists so Spatie doesn't throw
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'create_medical_record',
            'guard_name' => 'web',
        ]);

        // Create a patient user
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $token = $patient->createToken('test-token')->plainTextToken;

        // Create a doctor and clinic
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $clinic = Clinic::factory()->create();

        // Attempt to create a medical record
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/medical-records', [
                'patient_id' => $patient->id,
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
                'chief_complaint' => 'Headache',
            ]);

        // Assert that patient cannot create medical record (403)
        $response->assertStatus(403);
    }


    /**
     * Test: Admin can create medical records
     */
    public function test_admin_can_create_medical_record(): void
    {
        // Ensure permission exists
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'create_medical_record',
            'guard_name' => 'web',
        ]);

        // Create an admin user
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Give admin the permission directly
        $admin->givePermissionTo($permission);

        // Create doctor, clinic, and patient
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $clinic = Clinic::factory()->create();
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);

        // Attempt to create medical record
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/medical-records', [
                'patient_id' => $patient->id,
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
                'chief_complaint' => 'Fever',
            ]);

        // Assert success
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'uuid',
                'patient_id',
                'clinic_id',
                'doctor_id',
                'visit_date',
                'chief_complaint',
            ],
        ]);
    }



    /**
     * Test: List patient's medical records
     */
    public function test_patient_can_list_own_medical_records(): void
    {
        // Ensure permission exists
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        // Create a patient user
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $token = $patient->createToken('test-token')->plainTextToken;

        // Give patient the permission
        $patient->givePermissionTo($permission);

        // Create medical records for this patient
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $clinic = Clinic::factory()->create();

        MedicalRecord::factory()->count(2)->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        // Attempt to list patient's own medical records
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/patients/{$patient->id}/medical-records");

        // Assert success
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'uuid',
                    'patient_id',
                    'clinic_id',
                    'doctor_id',
                    'visit_date',
                    'chief_complaint',
                ],
            ],
        ]);
    }


    /**
     * Test: Patient cannot list other patient's medical records
     */
    public function test_patient_cannot_list_other_patient_records(): void
    {
        // Ensure the permission exists
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        $patient1 = User::factory()->create(['user_type' => UserType::PATIENT]);
        $patient2 = User::factory()->create(['user_type' => UserType::PATIENT]);
        $token = $patient1->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/patients/{$patient2->id}/medical-records");

        // Should now properly return 403 instead of 500
        $response->assertStatus(403);
    }


    /**
     * Test: Doctor can list patient's medical records
     */
    public function test_doctor_can_list_patient_medical_records(): void
    {
        // Ensure the permission exists
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        // Create doctor user and assign permission
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctorUser->givePermissionTo($permission);

        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // Create a patient and their medical records
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $clinic = Clinic::factory()->create();

        MedicalRecord::factory()->count(2)->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        // Make request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/patients/{$patient->id}/medical-records");

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }



    /**
     * Test: List clinic's medical records
     */
    public function test_can_list_clinic_medical_records(): void
    {
        // Make sure the permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        // Create a clinic
        $clinic = Clinic::factory()->create();

        // Create doctor user and doctor profile
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        // Assign permission to doctor so the middleware allows access
        $doctorUser->givePermissionTo($permission);

        // Generate Sanctum token
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // Create 5 medical records for this clinic
        MedicalRecord::factory()->count(5)->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
        ]);

        // Make API request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/clinics/{$clinic->id}/medical-records");

        // Assert success and correct number of records
        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    /**
     * Test: View specific medical record details
     */
    public function test_can_view_medical_record_details(): void
    {
        // Ensure permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        // Create doctor user and profile
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        // Assign permission
        $doctorUser->givePermissionTo($permission);

        // Generate token
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // Create patient, clinic, and medical record
        $patient = User::factory()->create();
        $clinic = Clinic::factory()->create();

        $record = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'chief_complaint' => 'Back pain',
        ]);

        // API request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$record->id}");

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonPath('data.chief_complaint', 'Back pain');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'uuid',
                'patient_id',
                'clinic_id',
                'doctor_id',
                'visit_date',
                'chief_complaint',
                'diagnosis',
                'treatment_plan',
                'notes',
                'is_visible_to_patient',
                'allergies',
                'medications',
            ],
        ]);
    }

    /**
     * Test: View medical record by UUID
     */
    public function test_can_view_medical_record_by_uuid(): void
    {
        // Ensure permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        // Create doctor user and profile
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        // Assign permission
        $doctorUser->givePermissionTo($permission);

        // Generate token
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // Create medical record
        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);

        // API request using UUID
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/medical-records/{$record->uuid}");

        // Assertions
        $response->assertStatus(200);

        // Compare UUID as string
        $this->assertEquals(
            (string)$record->uuid,
            $response->json('data.uuid')
        );
    }

    /**
     * Test: Update medical record
     */
    public function test_doctor_can_update_medical_record(): void
    {
        // Ensure permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'update_medical_record',
            'guard_name' => 'web',
        ]);

        // Create doctor user and profile
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        // Assign permission
        $doctorUser->givePermissionTo($permission);

        // Generate token
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // Create medical record for this doctor
        $record = MedicalRecord::factory()->create([
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Old diagnosis',
        ]);

        // API request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson($this->apiPrefix . "/medical-records/{$record->id}", [
                'diagnosis' => 'Updated diagnosis',
                'treatment_plan' => 'New treatment plan',
                'notes' => 'Updated notes',
            ]);

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonPath('data.diagnosis', 'Updated diagnosis');
        $response->assertJsonPath('data.treatment_plan', 'New treatment plan');

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'diagnosis' => 'Updated diagnosis',
        ]);
    }

    /**
     * Test: Different doctor cannot update another doctor's record
     */
    public function test_doctor_cannot_update_other_doctor_record(): void
    {
        // Ensure permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'update_medical_record',
            'guard_name' => 'web',
        ]);

        // Create doctor1
        $doctor1User = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor1 = Doctor::factory()->create(['user_id' => $doctor1User->id]);

        // Create doctor2
        $doctor2User = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor2 = Doctor::factory()->create(['user_id' => $doctor2User->id]);

        // Assign permission to doctor2 (who will attempt update)
        $doctor2User->givePermissionTo($permission);

        // Generate token for doctor2
        $token = $doctor2User->createToken('test-token')->plainTextToken;

        // Medical record belongs to doctor1
        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor1->id]);

        // API request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson($this->apiPrefix . "/medical-records/{$record->id}", [
                'diagnosis' => 'Updated diagnosis',
            ]);

        // Should be forbidden
        $response->assertStatus(403);
    }

    /**
     * Test: Admin can update any medical record
     */
    public function test_admin_can_update_any_medical_record(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'update_medical_record',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->givePermissionTo($permission);
        $token = $admin->createToken('test-token')->plainTextToken;
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson($this->apiPrefix . "/medical-records/{$record->id}", [
                'diagnosis' => 'Admin updated diagnosis',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.diagnosis', 'Admin updated diagnosis');

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'diagnosis' => 'Admin updated diagnosis',
        ]);
    }

    /**
     * Test: Delete (soft delete) medical record
     */
    public function test_doctor_can_delete_medical_record(): void
    {
        // Ensure permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'delete_medical_record',
            'guard_name' => 'web',
        ]);

        // Create doctor user and assign permission
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $doctorUser->givePermissionTo($permission);

        // Token for doctor
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        // Create medical record
        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);

        // API request
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson($this->apiPrefix . "/medical-records/{$record->id}");

        // Assertions
        $response->assertStatus(204);
        $this->assertSoftDeleted('medical_records', ['id' => $record->id]);
    }

    /**
     * Test: Patient cannot delete medical records
     */
    public function test_patient_cannot_delete_medical_record(): void
    {
        // Ensure the permission exists in the database
        Permission::firstOrCreate([
            'name' => 'delete_medical_record',
            'guard_name' => 'web',
        ]);

        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $token = $patient->createToken('test-token')->plainTextToken;

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson($this->apiPrefix . "/medical-records/{$record->id}");

        $response->assertStatus(403); // Should fail as patient has no permission
    }


    /**
     * Test: Restore deleted medical record
     */
    public function test_admin_can_restore_deleted_medical_record(): void
    {
        // Ensure permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'restore_medical_record',
            'guard_name' => 'web',
        ]);

        // Create admin user and assign permission
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->givePermissionTo($permission);

        $token = $admin->createToken('test-token')->plainTextToken;

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);
        $record->delete(); // Soft delete

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . "/medical-records/{$record->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted('medical_records', ['id' => $record->id]);
    }

    /**
     * Test: Non-admin cannot restore medical records
     */
    public function test_non_admin_cannot_restore_medical_record(): void
    {
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);
        $record->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . "/medical-records/{$record->id}/restore");

        $response->assertStatus(403);
    }

    /**
     * Test: Toggle visibility of medical record to patient
     */

    /**
     * Test: Patient cannot toggle visibility
     */
    public function test_patient_cannot_toggle_visibility(): void
    {
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $token = $patient->createToken('test-token')->plainTextToken;

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $record = MedicalRecord::factory()->create(['doctor_id' => $doctor->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson($this->apiPrefix . "/medical-records/{$record->id}/visibility");

        $response->assertStatus(403);
    }

    /**
     * Test: Auto-create medical record from completed appointment
     */
    public function test_medical_record_auto_created_from_completed_appointment(): void
    {
        // Ensure permission and role exist
        Permission::firstOrCreate(['name' => 'complete_appointment', 'guard_name' => 'web']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctorRole->givePermissionTo('complete_appointment');

        // Setup
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctorUser->assignRole('doctor'); // <-- important
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);

        $appointmentDateTime = now()->addDays(2)->setTime(10, 0, 0);
        $dayOfWeek = (int) $appointmentDateTime->dayOfWeek;
        $appointmentDate = $appointmentDateTime->toDateString();
        $appointmentTime = $appointmentDateTime->toDateTimeString();

        DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
            'status' => AppointmentStatus::CONFIRMED,
            'reason' => 'Regular checkup',
        ]);

        // Complete appointment
        $token = $doctorUser->createToken('test-token')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . "/appointments/{$appointment->id}/complete");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'appointment' => ['id', 'status'],
            'medical_record' => [
                'id',
                'uuid',
                'patient_id',
                'clinic_id',
                'doctor_id',
                'visit_date',
                'chief_complaint',
            ],
        ]);

        // Verify medical record was created
        $this->assertDatabaseHas('medical_records', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'chief_complaint' => 'Regular checkup',
        ]);

        $record = MedicalRecord::where('patient_id', $patient->id)->first();
        $this->assertEquals($appointmentDate, $record->visit_date->toDateString());
    }


    /**
     * Test: Medical record not created if appointment not completed
     */
    public function test_medical_record_not_created_if_appointment_pending(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $patient = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'status' => AppointmentStatus::PENDING,
        ]);

        $recordCount = MedicalRecord::where('patient_id', $patient->id)->count();
        $this->assertEquals(0, $recordCount);
    }

    /**
     * Test: Duplicate medical record not created for same appointment
     */
    public function test_duplicate_medical_record_not_created(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $patient = User::factory()->create();

        $appointmentDate = now()->toDateString();

        // Create existing medical record
        $existingRecord = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'visit_date' => $appointmentDate,
        ]);

        // Try to create from appointment on same date
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $appointmentDate,
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $token = $doctorUser->createToken('test-token')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . "/appointments/{$appointment->id}/complete");

        $recordCount = MedicalRecord::where('patient_id', $patient->id)->count();
        $this->assertEquals(1, $recordCount); // No duplicate created
    }

    /**
     * Test: Pagination on medical records list
     */
    public function test_medical_records_pagination(): void
    {
        // Ensure permission exists and assign to patient
        Permission::firstOrCreate([
            'name' => 'view_medical_records',
            'guard_name' => 'web',
        ]);

        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $patient->givePermissionTo('view_medical_records'); // <-- important

        $token = $patient->createToken('test-token')->plainTextToken;

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $clinic = Clinic::factory()->create();

        MedicalRecord::factory()->count(20)->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/patients/{$patient->id}/medical-records?per_page=10");

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(10, $response->json('meta.per_page'));
    }


    /**
     * Test: Medical record validation - invalid patient
     */
    public function test_create_medical_record_with_invalid_patient(): void
    {
        // Ensure permission exists and assign it
        Permission::firstOrCreate([
            'name' => 'create_medical_record',
            'guard_name' => 'web',
        ]);

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctorUser->givePermissionTo('create_medical_record'); // <-- important
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        $clinic = Clinic::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/medical-records', [
                'patient_id' => 99999, // Non-existent patient
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['patient_id']);
    }


    /**
     * Test: Medical record validation - future visit date
     */
    public function test_create_medical_record_with_future_date(): void
    {
        // Step 1: Create the permission if it doesn't exist
        Permission::firstOrCreate([
            'name' => 'create_medical_record',
            'guard_name' => 'web',
        ]);

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        // Step 2: Give permission to the doctor
        $doctorUser->givePermissionTo('create_medical_record');

        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        $clinic = Clinic::factory()->create();
        $patient = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/medical-records', [
                'patient_id' => $patient->id,
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->addDays(5)->toDateString(), // Future date
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visit_date']);
    }

    /**
     * Test: Medical record with array health history
     */
    public function test_create_medical_record_with_complete_health_history(): void
    {
        // Ensure the permission exists
        Permission::firstOrCreate([
            'name' => 'create_medical_record',
            'guard_name' => 'web',
        ]);

        $doctorUser = User::factory()->create(['user_type' => UserType::DOCTOR]);
        // Give permission to doctor
        $doctorUser->givePermissionTo('create_medical_record');

        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $token = $doctorUser->createToken('test-token')->plainTextToken;

        $clinic = Clinic::factory()->create();
        $patient = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/medical-records', [
                'patient_id' => $patient->id,
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
                'chief_complaint' => 'Regular checkup',
                'allergies' => ['penicillin', 'peanuts', 'shellfish'],
                'medications' => ['aspirin 500mg', 'vitamin D 2000IU', 'metformin 500mg'],
                'medical_history' => ['hypertension', 'diabetes type 2'],
                'family_history' => ['heart disease', 'cancer', 'alzheimers'],
                'social_history' => ['smoker', 'occasional alcohol use'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('medical_records', [
            'patient_id' => $patient->id,
            'chief_complaint' => 'Regular checkup',
        ]);

        // Verify arrays were stored correctly
        $record = MedicalRecord::where('patient_id', $patient->id)->first();
        $this->assertCount(3, $record->allergies);
        $this->assertCount(3, $record->medications);
        $this->assertCount(2, $record->medical_history);
    }

    /**
     * Test: Unauthorized access without token
     */
    public function test_cannot_access_without_authentication(): void
    {
        $record = MedicalRecord::factory()->create();

        $response = $this->getJson($this->apiPrefix . "/medical-records/{$record->id}");

        $response->assertStatus(401);
    }
}