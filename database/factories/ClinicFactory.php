<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            'uuid' => \Illuminate\Support\Str::uuid(),
            'clinic_name' => $this->faker->company(),
            'clinic_type' => $this->faker->randomElement(['private','public']),
            'owner_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => 'active',
            'description' => $this->faker->sentence(),
        ];
    }
}
