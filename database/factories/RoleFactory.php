<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'guard_name' => 'web',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'admin',
        ]);
    }

    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'doctor',
        ]);
    }

    public function secretary(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'secretary',
        ]);
    }

    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'patient',
        ]);
    }
}
