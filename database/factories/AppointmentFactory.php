<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        // Random appointment start between 1 and 10 days from now
        $start = $this->faker->dateTimeBetween('+1 days', '+10 days');
        $appointmentDate = Carbon::instance($start)->format('Y-m-d');
        $appointmentTime = Carbon::instance($start)->format('Y-m-d H:i:s'); 
        $dayOfWeek = Carbon::instance($start)->dayOfWeek;

        // Create related models
        $clinic = Clinic::factory()->create();
        $doctor = Doctor::factory()->create();

        // Ensure doctor has a schedule on that day
        DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_duration' => 30,
        ]);

        return [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => User::factory()->create()->id,
            'appointment_type' => $this->faker->randomElement(['in_person', 'telemedicine']),
            'status' => \App\Enums\AppointmentStatus::PENDING,
            'reason' => $this->faker->sentence(),
            'patient_notes' => $this->faker->optional()->paragraph(),
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
        ];
    }
}
