<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'license_number' => $this->faker->bothify('LIC-#####'),
            'specialty' => $this->faker->randomElement(['Cardiology','Dermatology','Pediatrics']),
        ];
    }
}
