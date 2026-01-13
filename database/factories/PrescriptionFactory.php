<?php
// ============================================
// database/factories/PrescriptionFactory.php
// ============================================

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\MedicalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $medicalRecord = MedicalRecord::factory()->create();

        return [
            'medical_record_id' => $medicalRecord->id,
            'prescribed_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'notes' => $this->faker->optional()->sentence(),
            'is_visible_to_patient' => $this->faker->boolean(80),
        ];
    }

    public function withoutNotes(): static
    {
        return $this->state(fn(array $attributes) => [
            'notes' => null,
        ]);
    }

    public function visibleToPatient(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_visible_to_patient' => true,
        ]);
    }

    public function hiddenFromPatient(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_visible_to_patient' => false,
        ]);
    }
}