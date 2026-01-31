<?php
// ============================================
// database/factories/NotificationFactory.php
// ============================================

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $types = [
            'appointment_confirmation',
            'appointment_reminder',
            'medical_record_shared',
            'prescription_added',
            'clinic_announcement',
            'system_notification',
        ];

        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement($types),
            'title' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(),
            'data' => [
                'related_id' => $this->faker->numberBetween(1, 100),
                'action_url' => $this->faker->url(),
            ],
            'read_at' => null,
            'sent_at' => now(),
            'scheduled_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now()->subHours(2),
        ]);
    }

    public function appointmentConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'appointment_confirmation',
            'title' => 'Appointment Confirmed',
            'message' => 'Your appointment has been confirmed.',
            'data' => [
                'appointment_id' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    public function appointmentReminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'appointment_reminder',
            'title' => 'Appointment Reminder',
            'message' => 'Your appointment is tomorrow at 2:00 PM',
            'data' => [
                'appointment_id' => $this->faker->numberBetween(1, 100),
                'hours_before' => 24,
            ],
        ]);
    }

    public function medicalRecordShared(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'medical_record_shared',
            'title' => 'Medical Record Available',
            'message' => 'Your medical record is now available.',
            'data' => [
                'medical_record_id' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    public function prescriptionAdded(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'prescription_added',
            'title' => 'New Prescription',
            'message' => 'A new prescription has been added.',
            'data' => [
                'prescription_id' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => now()->addHours($this->faker->numberBetween(1, 24)),
            'sent_at' => null,
        ]);
    }
}
