<?php
// ============================================
// tests/Unit/Services/NotificationServiceTest.php
// ============================================

namespace Tests\Unit\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * Test creating in-app notification
     */
    public function test_can_create_in_app_notification(): void
    {
        $user = User::factory()->create();

        $notification = $this->notificationService->createInAppNotification(
            userId: $user->id,
            type: 'test',
            title: 'Test Notification',
            message: 'This is a test notification',
            data: ['test' => true]
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals('test', $notification->type);
        $this->assertEquals('Test Notification', $notification->title);
        $this->assertEquals($user->id, $notification->user_id);
        $this->assertTrue($notification->data['test']);
    }

    /**
     * Test mark notification as read
     */
    public function test_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->unread()->create();

        $this->assertNull($notification->read_at);

        $this->notificationService->markAsRead($notification->id);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    /**
     * Test mark all notifications as read
     */
    public function test_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory(5)->unread()->create(['user_id' => $user->id]);

        $unreadBefore = Notification::where('user_id', $user->id)->unread()->count();
        $this->assertEquals(5, $unreadBefore);

        $this->notificationService->markAllAsRead($user->id);

        $unreadAfter = Notification::where('user_id', $user->id)->unread()->count();
        $this->assertEquals(0, $unreadAfter);
    }

    /**
     * Test get unread count
     */
    public function test_get_unread_count(): void
    {
        $user = User::factory()->create();
        Notification::factory(3)->unread()->create(['user_id' => $user->id]);
        Notification::factory(2)->read()->create(['user_id' => $user->id]);

        $unreadCount = $this->notificationService->getUnreadCount($user->id);

        $this->assertEquals(3, $unreadCount);
    }

    /**
     * Test get or create preference
     */
    public function test_get_or_create_notification_preference(): void
    {
        $user = User::factory()->create();

        $preference = $this->notificationService->getOrCreatePreference($user->id);

        $this->assertNotNull($preference);
        $this->assertEquals($user->id, $preference->user_id);
        $this->assertTrue($preference->appointment_confirmation);
        $this->assertTrue($preference->email_enabled);
    }

    /**
     * Test send appointment confirmation
     */
    public function test_can_send_appointment_confirmation(): void
    {
        $patient = User::factory()->create(['email' => 'patient@test.com']);
        $doctor = Doctor::factory()
            ->for(User::factory()->create(['email' => 'doctor@test.com']))
            ->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'appointment_confirmation' => true,
            'email_enabled' => true,
        ]);

        $appointment = Appointment::factory()
            ->for($patient, 'patient')
            ->for($doctor)
            ->for($clinic)
            ->create();

        $this->notificationService->sendAppointmentConfirmation($appointment);

        // Verify in-app notification created
        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'appointment_confirmation')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Appointment Confirmed', $notification->title);
    }

    public function test_send_appointment_confirmation_respects_channel_preferences(): void
    {
        $patient = User::factory()->create(['email' => 'patient@test.com']);
        $doctor = Doctor::factory()
            ->for(User::factory()->create(['email' => 'doctor@test.com']))
            ->create();
        $clinic = Clinic::factory()->create();

        // Disable both channels
        $patient->notificationPreference()->create([
            'appointment_confirmation' => true,
            'email_enabled' => false,
            'in_app_enabled' => false,
        ]);

        $appointment = Appointment::factory()
            ->for($patient, 'patient')
            ->for($doctor)
            ->for($clinic)
            ->create();

        $this->notificationService->sendAppointmentConfirmation($appointment);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'appointment_confirmation')
            ->first();

        $this->assertNull($notification);
    }

    /**
     * Test send appointment reminder respects preferences
     */
    public function test_appointment_reminder_respects_preferences(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()
            ->for(User::factory()->create())
            ->create();
        $clinic = Clinic::factory()->create();

        // Disable 24h reminders
        $patient->notificationPreference()->create([
            'appointment_reminder_24h' => false,
            'email_enabled' => true,
        ]);

        $appointment = Appointment::factory()
            ->for($patient, 'patient')
            ->for($doctor)
            ->for($clinic)
            ->create();

        // Should not create notification
        $this->notificationService->sendAppointmentReminder($appointment, 24);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'appointment_reminder')
            ->first();

        $this->assertNull($notification);
    }

    /**
     * Test send medical record shared notification
     */
    public function test_can_send_medical_record_shared_notification(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()
            ->for(User::factory()->create())
            ->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'medical_record_shared' => true,
            'in_app_enabled' => true,
        ]);

        $medicalRecord = \App\Models\MedicalRecord::factory()
            ->for($patient, 'patient')
            ->for($clinic)
            ->for($doctor)
            ->create();

        $this->notificationService->sendMedicalRecordShared($medicalRecord);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'medical_record_shared')
            ->first();

        $this->assertNotNull($notification);
    }

    /**
     * Test send prescription added notification
     */
    public function test_can_send_prescription_added_notification(): void
    {
        $patient = User::factory()->create();
        $doctor = Doctor::factory()
            ->for(User::factory()->create())
            ->create();
        $clinic = Clinic::factory()->create();

        $patient->notificationPreference()->create([
            'prescription_added' => true,
            'in_app_enabled' => true,
        ]);

        $medicalRecord = \App\Models\MedicalRecord::factory()
            ->for($patient, 'patient')
            ->for($clinic)
            ->for($doctor)
            ->create();

        $prescription = \App\Models\Prescription::factory()
            ->for($medicalRecord)
            ->create();

        $this->notificationService->sendPrescriptionAdded($prescription);

        $notification = Notification::where('user_id', $patient->id)
            ->where('type', 'prescription_added')
            ->first();

        $this->assertNotNull($notification);
    }
}