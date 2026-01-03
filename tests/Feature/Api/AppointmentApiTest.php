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
        // attach doctor to clinic via a schedule (doctor_schedules pivot)
        $doctor->clinics()->attach($clinic->id, ['day_of_week' => (int) now()->dayOfWeek, 'start_time' => now(), 'end_time' => now()->addHours(8), 'slot_duration' => 30]);

        $patient = User::factory()->create();

        $token = $patient->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDays(2)->toDateString(),
            'appointment_time' => now()->addDays(2)->addHours(10)->toDateTimeString(),
        ]);

        $resp->assertStatus(201);

        $appt = Appointment::first();
        $this->assertNotNull($appt);
        $this->assertNotNull($appt->patient_id);

        $cancel = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/cancel');
        $cancel->assertStatus(200)->assertJsonFragment(['status' => AppointmentStatus::CANCELLED->value]);

        $confirm = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/confirm');
        $confirm->assertStatus(200)->assertJsonFragment(['status' => AppointmentStatus::CONFIRMED->value]);

        $res = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/appointments/'.$appt->id.'/reschedule', [
            'scheduled_at' => now()->addDays(5)->toDateTimeString(),
        ]);
        $res->assertStatus(200)->assertJsonFragment(['status' => AppointmentStatus::Rescheduled->value]);
    }
}
