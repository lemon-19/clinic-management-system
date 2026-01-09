<?php

namespace Database\Factories;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'user_type' => UserType::PATIENT, // Default to PATIENT instead of random
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix_name' => fake()->optional()->randomElement(['Jr','Sr','III']),
            'gender' => fake()->randomElement(['male','female','other']),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an admin user
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => UserType::ADMIN,
        ]);
    }

    /**
     * Create a doctor user
     */
    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => UserType::DOCTOR,
        ]);
    }

    /**
     * Create a patient user
     */
    public function patient()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => \App\Enums\UserType::PATIENT,
            ];
        });
    }

    /**
     * Create a secretary user
     */
    public function secretary(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => UserType::SECRETARY,
        ]);
    }
}