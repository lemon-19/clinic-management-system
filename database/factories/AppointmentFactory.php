<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $starts = $this->faker->dateTimeBetween('+1 days', '+10 days');
        $ends = (clone $starts)->modify('+30 minutes');

        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'patient_id' => User::factory(),
            'appointment_type' => $this->faker->randomElement(['online','onsite']),
            'status' => 'pending',
            'notes' => $this->faker->sentence(),
            'starts_at' => $starts,
            'ends_at' => $ends,
        ];
    }
}
