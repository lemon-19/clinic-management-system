<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    public function definition(): array
    {
        return [
            'uuid' => \Illuminate\Support\Str::uuid(),
            'patient_id' => User::factory(),
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'visit_date' => $this->faker->dateTimeThisYear(),
            'chief_complaint' => $this->faker->sentence(),
            'diagnosis' => $this->faker->paragraph(),
            'treatment_plan' => $this->faker->paragraph(),
            'notes' => $this->faker->text(),
            'is_visible_to_patient' => true,
            'allergies' => ['penicillin', 'peanuts'],
            'medications' => ['aspirin 500mg'],
            'medical_history' => ['hypertension'],
            'family_history' => ['diabetes'],
            'social_history' => ['smoker'],
        ];
    }
}