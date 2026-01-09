<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Permission;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $actions = ['view', 'create', 'update', 'delete', 'restore'];
        $resources = ['clinic', 'doctor', 'appointment', 'medical_record', 'user'];

        return [
            'name' => fake()->unique()->word() . '_' . fake()->randomElement($resources),
            'guard_name' => 'web',
        ];
    }

    public function viewClinics(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'view_clinics',
        ]);
    }

    public function createClinic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'create_clinic',
        ]);
    }

    public function viewMedicalRecords(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'view_medical_records',
        ]);
    }

    public function createMedicalRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'create_medical_record',
        ]);
    }
}