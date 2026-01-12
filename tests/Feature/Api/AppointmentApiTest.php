<?php

namespace Tests\Feature\Api;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create all required permissions
        $this->createPermissions();
    }

    private function assignAdminRole(User $user): void
    {
        $adminRole = Role::findByName('admin', 'web');
        $user->assignRole($adminRole);
    }

    private function createPermissions(): void
    {
        $permissions = [
            'view_appointments',
            'create_appointment',
            'update_appointment',
            'cancel_appointment',
            'confirm_appointment',
            'complete_appointment',
            'view_medical_records',
            'create_medical_record',
            'update_medical_record',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create admin role and assign all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
        
        // Create patient role with basic permissions
        $patientRole = Role::firstOrCreate(['name' => 'patient', 'guard_name' => 'web']);
        $patientRole->syncPermissions([
            Permission::findByName('create_appointment', 'web'),
            Permission::findByName('cancel_appointment', 'web'),
            Permission::findByName('confirm_appointment', 'web'),
        ]);
    }

    private function assignPatientRole(User $user): void
    {
        $patientRole = Role::findByName('patient', 'web');
        $user->assignRole($patientRole);
    }


    public function test_can_create_and_cancel_confirm_reschedule(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointmentDateTime = now()->addDays(2)->setTime(10, 0, 0);
        $dayOfWeek = (int) $appointmentDateTime->dayOfWeek;
        $appointmentDate = $appointmentDateTime->toDateString();
        $appointmentTime = $appointmentDateTime->toDateTimeString();

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $patient = User::factory()->create();
        $this->assignPatientRole($patient);
        $token = $patient->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
        ]);

        $resp->assertStatus(201);
    }

    public function test_prevents_double_booking(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        $day = now()->addDays(2)->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $startTime = now()->addDays(2)->setTime(10, 0, 0)->toDateTimeString();

        $patient1 = User::factory()->create();
        $this->assignPatientRole($patient1);
        $token1 = $patient1->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/appointments', [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient1->id,
                'appointment_date' => now()->addDays(2)->toDateString(),
                'appointment_time' => $startTime,
            ]);
        $resp->assertStatus(201);

        $patient2 = User::factory()->create();
        $this->assignPatientRole($patient2);
        $token2 = $patient2->createToken('t')->plainTextToken;

        $resp2 = $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/appointments', [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient2->id,
                'appointment_date' => now()->addDays(2)->toDateString(),
                'appointment_time' => $startTime,
            ]);

        $resp2->assertStatus(422);
    }


    public function test_status_transitions(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        
        $start = now()->addDays(2)->setTime(10, 0, 0);
        $day = (int) $start->dayOfWeek;
        
        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30
        ]);

        $patient = User::factory()->create();
        $this->assignPatientRole($patient);
        $token = $patient->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $start->toDateString(),
            'appointment_time' => $start->toDateTimeString(),
        ]);

        $resp->assertStatus(201);
        $appt = Appointment::first();
        $this->assertEquals('pending', $appt->status->value);

        $confirm = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/confirm');
        $confirm->assertStatus(200)->assertJsonFragment(['status' => 'confirmed']);

        $appt->refresh();
        $this->assertEquals('confirmed', $appt->status->value);
    }

    public function test_bulk_cancel_multiple_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        $patient = User::factory()->create();
        $this->assignPatientRole($patient); // ✅ REQUIRED
        $token = $patient->createToken('t')->plainTextToken;

        $start = now()->addDays(2)->addHours(10);
        $day = (int) $start->dayOfWeek;
        
        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30
        ]);

        $appt1 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(10)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        $appt2 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(10)->addMinutes(30)->toDateTimeString(),
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $appt3 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(11)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson(
            '/api/v1/appointments/bulk-cancel',
            [
                'appointment_ids' => [$appt1->id, $appt2->id, $appt3->id],
                'reason' => 'Doctor unavailable'
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['count' => 3, 'failed' => 0]);
    }

    public function test_bulk_cancel_with_partial_failure(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        $patient = User::factory()->create();
        $this->assignPatientRole($patient);
        $token = $patient->createToken('t')->plainTextToken;

        // Create schedule
        $start = now()->addDays(2)->addHours(10);
        $day = (int) $start->dayOfWeek;
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $day,
            'start_time' => $start->copy()->setTime(8, 0),
            'end_time' => $start->copy()->setTime(18, 0),
            'slot_duration' => 30
        ]);

        // Create appointments with different statuses
        $appt1 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(10)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        $appt2 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(10)->addMinutes(30)->toDateTimeString(),
            'status' => AppointmentStatus::COMPLETED, // Cannot cancel completed
        ]);

        // Bulk cancel
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson(
            '/api/v1/appointments/bulk-cancel',
            [
                'appointment_ids' => [$appt1->id, $appt2->id],
                'reason' => 'Test cancellation'
            ]
        );

        $response->assertStatus(207);
        $response->assertJsonFragment([
            'count' => 1,
            'failed' => 1
        ]);

        // Verify results
        $this->assertEquals(AppointmentStatus::CANCELLED->value, $appt1->fresh()->status->value);
        $this->assertEquals(AppointmentStatus::COMPLETED->value, $appt2->fresh()->status->value);
    }

    public function test_bulk_reschedule_conflicting_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin', 'web'));
        $token = $admin->createToken('t')->plainTextToken;

        $patient1 = User::factory()->create();
        $patient2 = User::factory()->create();

        $sourceDate = now()->addDays(2)->setTime(0, 0, 0);
        $sourceDay = (int) $sourceDate->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $sourceDay,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $destDate = now()->addDays(5)->setTime(0, 0, 0);
        $destDay = (int) $destDate->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $destDay,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $appt1 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient1->id,
            'appointment_date' => $sourceDate->toDateString(),
            'appointment_time' => $sourceDate->copy()->setTime(10, 0)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        $appt2 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient2->id,
            'appointment_date' => $sourceDate->toDateString(),
            'appointment_time' => $sourceDate->copy()->setTime(11, 0)->toDateTimeString(),
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson(
            '/api/v1/appointments/bulk-reschedule-conflicts',
            [
                'doctor_id' => $doctor->id,
                'clinic_id' => $clinic->id,
                'date_from' => $sourceDate->toDateString(),
                'date_to' => $sourceDate->toDateString(),
                'new_date' => $destDate->toDateString(),
                'new_time' => '14:00',
            ]
        );

        $response->assertStatus(207);
        $response->assertJsonFragment([
            'count' => 1,
            'failed' => 1,
        ]);

        $this->assertEquals(
            $destDate->toDateString(),
            $appt1->fresh()->appointment_date->toDateString()
        );

        $this->assertEquals(
            $sourceDate->toDateString(),
            $appt2->fresh()->appointment_date->toDateString()
        );
    }


    public function test_bulk_reschedule_respects_availability(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin', 'web'));
        $token = $admin->createToken('t')->plainTextToken;

        $patient1 = User::factory()->create();
        $patient2 = User::factory()->create();

        $sourceDate = now()->addDays(2)->setTime(0, 0, 0);
        $sourceDay = (int) $sourceDate->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $sourceDay,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $destDate = now()->addDays(5)->setTime(0, 0, 0);
        $destDay = (int) $destDate->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $destDay,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $apptToReschedule = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient1->id,
            'appointment_date' => $sourceDate->toDateString(),
            'appointment_time' => $sourceDate->copy()->setTime(10, 0)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient2->id,
            'appointment_date' => $destDate->toDateString(),
            'appointment_time' => $destDate->copy()->setTime(14, 0)->toDateTimeString(),
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson(
            '/api/v1/appointments/bulk-reschedule-conflicts',
            [
                'doctor_id' => $doctor->id,
                'clinic_id' => $clinic->id,
                'date_from' => $sourceDate->toDateString(),
                'date_to' => $sourceDate->toDateString(),
                'new_date' => $destDate->toDateString(),
                'new_time' => '14:00',
            ]
        );

        $response->assertStatus(207);
        $response->assertJsonFragment([
            'count' => 0,
            'failed' => 1,
        ]);

        $this->assertEquals(
            $sourceDate->toDateString(),
            $apptToReschedule->fresh()->appointment_date->toDateString()
        );
    }



    public function test_unauthenticated_user_gets_401_on_create(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        
        $response = $this->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(10)->toDateTimeString(),
        ]);
        
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_gets_401_on_cancel(): void
    {
        $appointment = Appointment::factory()->create();
        
        $response = $this->postJson('/api/v1/appointments/' . $appointment->id . '/cancel');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_gets_401_on_confirm(): void
    {
        $appointment = Appointment::factory()->create();
        
        $response = $this->postJson('/api/v1/appointments/' . $appointment->id . '/confirm');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_gets_401_on_reschedule(): void
    {
        $appointment = Appointment::factory()->create();
        
        $response = $this->postJson('/api/v1/appointments/' . $appointment->id . '/reschedule', [
            'appointment_date' => now()->addDays(5)->toDateString(),
            'appointment_time' => now()->addDays(5)->addHours(10)->toDateTimeString(),
        ]);
        
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_gets_401_on_bulk_cancel(): void
    {
        $response = $this->postJson('/api/v1/appointments/bulk-cancel', [
            'appointment_ids' => [1, 2, 3],
            'reason' => 'Test'
        ]);
        
        $response->assertStatus(401);
    }

    /**
     * Test that users cannot cancel appointments they don't own
     */
    public function test_user_cannot_cancel_someone_elses_appointment(): void
    {
        $patient1 = User::factory()->create();
        $this->assignPatientRole($patient1);
        
        $patient2 = User::factory()->create();
        $this->assignPatientRole($patient2);
        $token2 = $patient2->createToken('api')->plainTextToken;

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient1->id,
        ]);

        $cancelResponse = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson("/api/v1/appointments/{$appointment->id}/cancel");

        $cancelResponse->assertStatus(403);
        $this->assertEquals(AppointmentStatus::PENDING->value, $appointment->fresh()->status->value);
    }



    /**
     * Test that users cannot reschedule appointments they don't own
     */
    public function test_user_cannot_reschedule_someone_elses_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        // Create schedule for original appointment date
        $appointmentDateTime = now()->addDays(2)->setTime(10, 0, 0);
        $dayOfWeek = (int) $appointmentDateTime->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        // Create schedule for reschedule date
        $rescheduleDateTime = now()->addDays(5)->setTime(10, 0, 0);
        $rescheduleDayOfWeek = (int) $rescheduleDateTime->dayOfWeek;

        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $rescheduleDayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        // Patient 1 (owner) — CAN create appointment
        $patient1 = User::factory()->create();
        $this->assignPatientRole($patient1);
        $token1 = $patient1->createToken('t')->plainTextToken;

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/v1/appointments', [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient1->id,
                'appointment_date' => $appointmentDateTime->toDateString(),
                'appointment_time' => $appointmentDateTime->toDateTimeString(),
            ]);

        $createResponse->assertStatus(201);
        $appointmentId = $createResponse->json('id');

        // Patient 2 (NOT owner)
        $patient2 = User::factory()->create();
        $this->assignPatientRole($patient2);
        $token2 = $patient2->createToken('t')->plainTextToken;

        // Attempt to reschedule someone else's appointment
        $rescheduleResponse = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
                'appointment_date' => $rescheduleDateTime->toDateString(),
                'appointment_time' => $rescheduleDateTime->toDateTimeString(),
            ]);

        // ❌ Forbidden
        $rescheduleResponse->assertStatus(403);
    }




}

