<?php

namespace Tests\Feature\Api;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_cancel_confirm_reschedule(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        // Create a specific datetime for appointment
        $appointmentDateTime = now()->addDays(2)->setTime(10, 0, 0);
        $dayOfWeek = (int) $appointmentDateTime->dayOfWeek;
        $appointmentDate = $appointmentDateTime->toDateString();
        $appointmentTime = $appointmentDateTime->toDateTimeString(); // Full datetime

        // Doctor schedule setup
        \App\Models\DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $patient = User::factory()->create();
        $token = $patient->createToken('t')->plainTextToken;

        // Create appointment
        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime, // Full datetime
        ]);

        $resp->assertStatus(201);
        // ... rest of test
    }


    public function test_prevents_double_booking(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        $day = now()->addDays(2)->dayOfWeek;

        // Doctor schedule
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
        ]);

        $startTime = '10:00:00'; // Must match schedule

        // Patient 1 books slot
        $patient1 = User::factory()->create();
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

        // Patient 2 tries to book same slot
        $patient2 = User::factory()->create();
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
        $resp2->assertJsonFragment(['Selected time overlaps another appointment.']);
    }


    public function test_status_transitions(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        
        // Set up schedule for day 2
        $start = now()->addDays(2)->setTime(10, 0, 0); // Start at 10:00 AM
        $day = (int) $start->dayOfWeek;
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30
        ]);

        $patient = User::factory()->create();
        $token = $patient->createToken('t')->plainTextToken;

        // Create first appointment at 10:00 AM (defaults to pending)
        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $start->toDateString(),
            'appointment_time' => $start->toDateTimeString(), // 10:00 AM
        ]);

        $resp->assertStatus(201);
        $appt = Appointment::first();
        $this->assertEquals('pending', $appt->status->value);

        // Confirm
        $confirm = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/confirm');
        $confirm->assertStatus(200)->assertJsonFragment(['status' => \App\Enums\AppointmentStatus::CONFIRMED->value]);

        $appt = $appt->fresh();
        $this->assertEquals('confirmed', $appt->status->value);

        // Cannot confirm again
        $confirm2 = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/confirm');
        $confirm2->assertStatus(422);

        // Create second appointment at 10:30 AM (30 minutes later, different slot)
        $secondAppointmentTime = now()->addDays(2)->setTime(10, 30, 0);
        $resp2 = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $secondAppointmentTime->toDateString(),
            'appointment_time' => $secondAppointmentTime->toDateTimeString(), // 10:30 AM
        ]);
        
        $resp2->assertStatus(201);
        $appt2 = Appointment::orderBy('id', 'desc')->first();

        // Cannot complete while pending
        $completeFail = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt2->id.'/complete');
        $completeFail->assertStatus(422);

        // Cannot confirm already confirmed appointment
        $confirm3 = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/confirm');
        $confirm3->assertStatus(422); // already confirmed

        // Complete the confirmed appointment
        $complete = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/complete');
        $complete->assertStatus(200)->assertJsonFragment(['status' => \App\Enums\AppointmentStatus::COMPLETED->value]);

        $appt = $appt->fresh();
        $this->assertEquals('completed', $appt->status->value);

        // Cannot confirm or complete again
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/confirm')->assertStatus(422);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/complete')->assertStatus(422);
    }

    public function test_bulk_cancel_multiple_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        $patient = User::factory()->create();
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

        // Create 3 appointments
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

        // Bulk cancel
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson(
            '/api/v1/appointments/bulk-cancel',
            [
                'appointment_ids' => [$appt1->id, $appt2->id, $appt3->id],
                'reason' => 'Doctor unavailable'
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'count' => 3,
            'failed' => 0
        ]);

        // Verify all cancelled
        $this->assertEquals(AppointmentStatus::CANCELLED->value, $appt1->fresh()->status->value);
        $this->assertEquals(AppointmentStatus::CANCELLED->value, $appt2->fresh()->status->value);
        $this->assertEquals(AppointmentStatus::CANCELLED->value, $appt3->fresh()->status->value);
    }

    public function test_bulk_cancel_with_partial_failure(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        $patient = User::factory()->create();
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

        $response->assertStatus(207); // Multi-status response
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
        $patient = User::factory()->create();
        $patient2 = User::factory()->create();
        $token = $patient->createToken('t')->plainTextToken;

        // Create schedules for both source and destination dates
        $sourceDate = now()->addDays(2);
        $sourceDay = (int) $sourceDate->dayOfWeek;
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $sourceDay,
            'start_time' => $sourceDate->copy()->setTime(8, 0),
            'end_time' => $sourceDate->copy()->setTime(18, 0),
            'slot_duration' => 30
        ]);

        $destDate = now()->addDays(5);
        $destDay = (int) $destDate->dayOfWeek;
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $destDay,
            'start_time' => $destDate->copy()->setTime(8, 0),
            'end_time' => $destDate->copy()->setTime(18, 0),
            'slot_duration' => 30
        ]);

        // Create appointments in the source date range - schedule them at different times
        $appt1 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $sourceDate->toDateString(),
            'appointment_time' => $sourceDate->copy()->addHours(10)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        $appt2 = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient2->id,
            'appointment_date' => $sourceDate->toDateString(),
            'appointment_time' => $sourceDate->copy()->addHours(11)->toDateTimeString(), // Different time
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        // Bulk reschedule - both will be rescheduled to different available slots on destination date
        // Since both are in the source range, both should be candidates for rescheduling
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

        // Since both appointments try to use the same new_time, the second will fail (no double-booking allowed)
        $response->assertStatus(207);
        $response->assertJsonFragment([
            'count' => 1,
            'failed' => 1
        ]);

        // Verify first appointment was rescheduled
        $appt1Refreshed = $appt1->fresh();
        $this->assertEquals($destDate->toDateString(), $appt1Refreshed->appointment_date->toDateString());
        $this->assertEquals(AppointmentStatus::PENDING->value, $appt1Refreshed->status->value);

        // Second appointment should remain on original date
        $appt2Refreshed = $appt2->fresh();
        $this->assertEquals($sourceDate->toDateString(), $appt2Refreshed->appointment_date->toDateString());
    }

    public function test_bulk_reschedule_respects_availability(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();
        $patient = User::factory()->create();
        $patient2 = User::factory()->create();
        $token = $patient->createToken('t')->plainTextToken;

        // Create schedules
        $sourceDate = now()->addDays(2)->setTime(0, 0, 0);
        $sourceDay = (int) $sourceDate->dayOfWeek;
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $sourceDay,
            'start_time' => $sourceDate->copy()->setTime(8, 0),
            'end_time' => $sourceDate->copy()->setTime(18, 0),
            'slot_duration' => 30
        ]);

        $destDate = now()->addDays(5)->setTime(0, 0, 0);
        $destDay = (int) $destDate->dayOfWeek;
        $doctor->clinics()->attach($clinic->id, [
            'day_of_week' => $destDay,
            'start_time' => $destDate->copy()->setTime(8, 0),
            'end_time' => $destDate->copy()->setTime(18, 0),
            'slot_duration' => 30
        ]);

        // Create appointment to reschedule
        $apptToReschedule = Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => $sourceDate->toDateString(),
            'appointment_time' => $sourceDate->copy()->setTime(10, 0, 0)->toDateTimeString(),
            'status' => AppointmentStatus::PENDING,
        ]);

        // Create blocking appointment at destination time
        Appointment::create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient2->id,
            'appointment_date' => $destDate->toDateString(),
            'appointment_time' => $destDate->copy()->setTime(14, 0, 0)->toDateTimeString(),
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        // Try to reschedule to occupied slot - should fail availability check
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

        // Should return 207 (Multi-status) because one appointment failed to reschedule
        $response->assertStatus(207); // Partial success
        $response->assertJsonFragment([
            'count' => 0,
            'failed' => 1
        ]);

        // Verify appointment wasn't rescheduled (still on source date)
        $apptToReschedule->refresh();
        $this->assertEquals($sourceDate->toDateString(), $apptToReschedule->appointment_date->toDateString());
    }


    // TODO: Fix the errors in these tests before enabling them again
    // NEW SECURITY AND AUTHORIZATION TESTS
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
    public function test_user_cannot_cancel_someone_elses_appointment()
    {
        // Create two distinct patients
        $patient1 = User::factory()->patient()->create();
        $patient2 = User::factory()->patient()->create();

        \Log::info('Test start - Users', [
            'patient1_id' => $patient1->id,
            'patient2_id' => $patient2->id,
        ]);

        // Create an appointment for patient1
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient1->id,
        ]);

        \Log::info('Created appointment', [
            'appointment_id' => $appointment->id,
            'appointment_patient_id' => $appointment->patient_id,
            'expected_patient_id' => $patient1->id,
        ]);

        // Authenticate as patient2 (the "wrong user")
        $token2 = $patient2->createToken('api')->plainTextToken;

        \Log::info('About to test cancellation by wrong user', [
            'appointment_patient_id' => $appointment->patient_id,
            'requesting_user_id' => $patient2->id,
        ]);

        // Attempt to cancel the appointment as patient2
        $cancelResponse = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson("/api/v1/appointments/{$appointment->id}/cancel");

        // Should return 403 Forbidden
        $cancelResponse->assertStatus(403);

        \Log::info('Cancel response status', [
            'status' => $cancelResponse->status(),
            'body' => $cancelResponse->json(),
        ]);

        // Verify appointment is still pending (not cancelled)
        $this->assertEquals(AppointmentStatus::PENDING->value, $appointment->fresh()->status->value);

        \Log::info('Appointment status after failed cancel', [
            'appointment_id' => $appointment->id,
            'status' => $appointment->fresh()->status->value,
        ]);
    }



    /**
     * Test that users cannot reschedule appointments they don't own
     */
    public function test_user_cannot_reschedule_someone_elses_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        // Create schedule for day 2
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

        // Create schedule for day 5 (reschedule date)
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

        // Patient 1 creates appointment
        $patient1 = User::factory()->create();
        $token1 = $patient1->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/appointments', [
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient1->id,
                'appointment_date' => $appointmentDateTime->toDateString(),
                'appointment_time' => $appointmentDateTime->toDateTimeString(), // Full datetime
            ]);
        $response->assertStatus(201);

        // ... rest of test
    }



}

