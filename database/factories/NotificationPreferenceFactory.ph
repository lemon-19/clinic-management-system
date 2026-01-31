<?php
// ============================================
// database/factories/NotificationPreferenceFactory.php
// ============================================

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'appointment_confirmation' => true,
            'appointment_reminder_24h' => true,
            'appointment_reminder_1h' => true,
            'appointment_completed' => true,
            'medical_record_shared' => true,
            'prescription_added' => true,
            'test_results_ready' => true,
            'clinic_announcements' => true,
            'system_notifications' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'in_app_enabled' => true,
            'reminder_24h_time' => '09:00',
            'reminder_1h_time' => null,
        ];
    }

    public function emailOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_enabled' => true,
            'sms_enabled' => false,
            'in_app_enabled' => false,
        ]);
    }

    public function inAppOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_enabled' => false,
            'sms_enabled' => false,
            'in_app_enabled' => true,
        ]);
    }

    public function allChannelsDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_enabled' => false,
            'sms_enabled' => false,
            'in_app_enabled' => false,
        ]);
    }

    public function appointmentsDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_confirmation' => false,
            'appointment_reminder_24h' => false,
            'appointment_reminder_1h' => false,
            'appointment_completed' => false,
        ]);
    }
}
