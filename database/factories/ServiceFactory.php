<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 0, 500),
        ];
    }
}
