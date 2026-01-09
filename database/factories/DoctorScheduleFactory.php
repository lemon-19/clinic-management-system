<?php

namespace Database\Factories;

use App\Models\DoctorSchedule;
use App\Models\Doctor;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorScheduleFactory extends Factory
{
    protected $model = DoctorSchedule::class;

    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'clinic_id' => Clinic::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'slot_duration' => 30,
            'is_available' => true,
        ];
    }
}