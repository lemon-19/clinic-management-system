<?php
// ============================================
// database/factories/PrescriptionMedicationFactory.php
// ============================================

namespace Database\Factories;

use App\Models\PrescriptionMedication;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionMedicationFactory extends Factory
{
    protected $model = PrescriptionMedication::class;

    public function definition(): array
    {
        $medications = [
            'Paracetamol',
            'Ibuprofen',
            'Amoxicillin',
            'Metformin',
            'Lisinopril',
            'Atorvastatin',
            'Aspirin',
            'Omeprazole',
        ];

        return [
            'prescription_id' => Prescription::factory(),
            'medication_name' => $this->faker->randomElement($medications),
            'dosage' => $this->faker->randomElement(['250mg', '500mg', '750mg', '1000mg']),
            'frequency' => $this->faker->randomElement(['Once daily', 'Twice daily', 'Three times daily', 'As needed']),
            'duration' => $this->faker->randomElement(['5 days', '7 days', '10 days', '14 days', '30 days']),
            'instructions' => $this->faker->optional()->sentence(),
            'quantity' => $this->faker->numberBetween(1, 3),
        ];
    }

    public function withPrescription(Prescription $prescription): static
    {
        return $this->state(fn(array $attributes) => [
            'prescription_id' => $prescription->id,
        ]);
    }
}