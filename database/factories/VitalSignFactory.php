<?php

namespace Database\Factories;

use App\Models\VitalSign;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalSign>
 */
class VitalSignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get or create a medical record
        $medicalRecord = MedicalRecord::factory()->create();
        
        // Get or create a user to record the vital signs
        $recordedBy = User::factory()->create();

        // Generate realistic vital sign measurements
        $weight = $this->faker->numberBetween(45, 120); // kg
        $height = $this->faker->numberBetween(150, 200); // cm
        
        // Calculate BMI: weight (kg) / (height (m))^2
        $heightInMeters = $height / 100;
        $bmi = round($weight / ($heightInMeters ** 2), 2);

        return [
            'medical_record_id' => $medicalRecord->id,
            'recorded_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'weight' => $weight,
            'height' => $height,
            'bmi' => $bmi,
            'blood_pressure_systolic' => $this->faker->numberBetween(100, 160),
            'blood_pressure_diastolic' => $this->faker->numberBetween(60, 100),
            'heart_rate' => $this->faker->numberBetween(60, 100),
            'temperature' => $this->faker->randomFloat(1, 36.0, 38.5),
            'respiratory_rate' => $this->faker->numberBetween(12, 20),
            'oxygen_saturation' => $this->faker->numberBetween(95, 100),
            'blood_sugar' => $this->faker->randomFloat(2, 80, 150),
            'recorded_by' => $recordedBy->id,
        ];
    }

    /**
     * State: High blood pressure
     */
    public function highBloodPressure(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'blood_pressure_systolic' => $this->faker->numberBetween(140, 180),
                'blood_pressure_diastolic' => $this->faker->numberBetween(90, 120),
            ];
        });
    }

    /**
     * State: Normal blood pressure
     */
    public function normalBloodPressure(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'blood_pressure_systolic' => $this->faker->numberBetween(110, 130),
                'blood_pressure_diastolic' => $this->faker->numberBetween(70, 85),
            ];
        });
    }

    /**
     * State: High heart rate (abnormal)
     */
    public function highHeartRate(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'heart_rate' => $this->faker->numberBetween(101, 150),
            ];
        });
    }

    /**
     * State: Low heart rate (abnormal)
     */
    public function lowHeartRate(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'heart_rate' => $this->faker->numberBetween(30, 59),
            ];
        });
    }

    /**
     * State: Normal heart rate
     */
    public function normalHeartRate(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'heart_rate' => $this->faker->numberBetween(60, 100),
            ];
        });
    }

    /**
     * State: High temperature (fever)
     */
    public function fever(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'temperature' => $this->faker->randomFloat(1, 38.0, 40.0),
            ];
        });
    }

    /**
     * State: Normal temperature
     */
    public function normalTemperature(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'temperature' => $this->faker->randomFloat(1, 36.5, 37.5),
            ];
        });
    }

    /**
     * State: Underweight BMI
     */
    public function underweight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->numberBetween(160, 180);
            // BMI < 18.5, so weight should be less
            $weight = $this->faker->numberBetween(40, 53);
            $heightInMeters = $height / 100;
            $bmi = round($weight / ($heightInMeters ** 2), 2);

            return [
                'weight' => $weight,
                'height' => $height,
                'bmi' => $bmi,
            ];
        });
    }

    /**
     * State: Normal weight BMI
     */
    public function normalWeight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->numberBetween(160, 180);
            // BMI between 18.5 and 24.9
            $weight = $this->faker->numberBetween(47, 80);
            $heightInMeters = $height / 100;
            $bmi = round($weight / ($heightInMeters ** 2), 2);

            return [
                'weight' => $weight,
                'height' => $height,
                'bmi' => $bmi,
            ];
        });
    }

    /**
     * State: Overweight BMI
     */
    public function overweight(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->numberBetween(160, 180);
            // BMI between 25 and 29.9
            $weight = $this->faker->numberBetween(80, 95);
            $heightInMeters = $height / 100;
            $bmi = round($weight / ($heightInMeters ** 2), 2);

            return [
                'weight' => $weight,
                'height' => $height,
                'bmi' => $bmi,
            ];
        });
    }

    /**
     * State: Obese BMI
     */
    public function obese(): static
    {
        return $this->state(function (array $attributes) {
            $height = $this->faker->numberBetween(160, 180);
            // BMI >= 30
            $weight = $this->faker->numberBetween(95, 150);
            $heightInMeters = $height / 100;
            $bmi = round($weight / ($heightInMeters ** 2), 2);

            return [
                'weight' => $weight,
                'height' => $height,
                'bmi' => $bmi,
            ];
        });
    }

    /**
     * State: Low oxygen saturation
     */
    public function lowOxygenSaturation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'oxygen_saturation' => $this->faker->numberBetween(85, 94),
            ];
        });
    }

    /**
     * State: Normal oxygen saturation
     */
    public function normalOxygenSaturation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'oxygen_saturation' => $this->faker->numberBetween(95, 100),
            ];
        });
    }

    /**
     * State: High blood sugar
     */
    public function highBloodSugar(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'blood_sugar' => $this->faker->randomFloat(2, 150, 200),
            ];
        });
    }

    /**
     * State: Normal blood sugar
     */
    public function normalBloodSugar(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'blood_sugar' => $this->faker->randomFloat(2, 70, 100),
            ];
        });
    }

    /**
     * State: All measurements normal
     */
    public function allNormal(): static
    {
        return $this->normalBloodPressure()
            ->normalHeartRate()
            ->normalTemperature()
            ->normalOxygenSaturation()
            ->normalBloodSugar()
            ->normalWeight();
    }

    /**
     * State: For a specific medical record
     */
    public function forMedicalRecord($medicalRecordId): static
    {
        return $this->state(function (array $attributes) use ($medicalRecordId) {
            return [
                'medical_record_id' => $medicalRecordId,
            ];
        });
    }

    /**
     * State: With specific recorder
     */
    public function recordedBy($userId): static
    {
        return $this->state(function (array $attributes) use ($userId) {
            return [
                'recorded_by' => $userId,
            ];
        });
    }

    /**
     * State: With specific recorded date
     */
    public function recordedAt($dateTime): static
    {
        return $this->state(function (array $attributes) use ($dateTime) {
            return [
                'recorded_at' => $dateTime,
            ];
        });
    }
}