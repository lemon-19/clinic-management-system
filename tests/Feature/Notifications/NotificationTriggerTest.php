<?php
// ============================================
// tests/Feature/Notifications/NotificationTriggerTest.php
// ============================================

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Notification;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTriggerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test appointment confirmation triggers notification
     */
    public function test_appointment_confirmation_triggers_notification(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()->for(User::factory()->create())->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'appointment_confirmation' => true,
        ]);

        $appointment = Appointment::factory()
            ->for($patient, 'patient')
            ->for($doctor)
            ->for($clinic)
            ->create(['status' => 'pending']);

        // Update to confirmed status
        $appointment->update(['status' => 'confirmed']);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'appointment_confirmation')
            ->first();

        $this->assertNotNull($notification);
    }

    /**
     * Test medical record share triggers notification
     */
    public function test_medical_record_share_triggers_notification(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()->for(User::factory()->create())->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'medical_record_shared' => true,
        ]);

        $medicalRecord = MedicalRecord::factory()
            ->for($patient, 'patient')
            ->for($clinic)
            ->for($doctor)
            ->create(['is_visible_to_patient' => false]);

        // Make visible to patient
        $medicalRecord->update(['is_visible_to_patient' => true]);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'medical_record_shared')
            ->first();

        $this->assertNotNull($notification);
    }

    /**
     * Test prescription added triggers notification
     */
    public function test_prescription_added_triggers_notification(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()->for(User::factory()->create())->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'prescription_added' => true,
        ]);

        $medicalRecord = MedicalRecord::factory()
            ->for($patient, 'patient')
            ->for($clinic)
            ->for($doctor)
            ->create();

        $prescription = Prescription::factory()
            ->for($medicalRecord)
            ->create(['is_visible_to_patient' => true]);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'prescription_added')
            ->first();

        $this->assertNotNull($notification);
    }

    /**
     * Test notifications respect preferences
     */
    public function test_disabled_notification_type_not_triggered(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()->for(User::factory()->create())->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'appointment_confirmation' => false,
        ]);

        $appointment = Appointment::factory()
            ->for($patient, 'patient')
            ->for($doctor)
            ->for($clinic)
            ->create(['status' => 'pending']);

        $appointment->update(['status' => 'confirmed']);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'appointment_confirmation')
            ->first();

        $this->assertNull($notification);
    }
}