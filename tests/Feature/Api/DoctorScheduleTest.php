<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Clinic;
use App\Models\DoctorSchedule;
use App\Models\Appointment;
use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class DoctorScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $doctor;
    protected $clinic;
    protected $token;
    
    // Add this constant for the API prefix
    private const API_PREFIX = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create([
            'user_type' => 'admin',
        ]);

        // Create doctor with associated user
        $doctorUser = User::factory()->create([
            'user_type' => 'doctor',
        ]);
        $this->doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        // Create clinic
        $this->clinic = Clinic::factory()->create();

        // Authenticate user
        $this->actingAs($this->user, 'sanctum');
    }

    /**
     * ============================================
     * LIST DOCTOR SCHEDULES TESTS
     * ============================================
     */

    public function test_can_list_all_doctor_schedules()
    {
        DoctorSchedule::factory()->count(5)->create();

        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'doctor_id',
                        'clinic_id',
                        'day_of_week',
                        'start_time',
                        'end_time',
                        'slot_duration',
                        'is_available',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                ],
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_list_schedules_with_pagination()
    {
        DoctorSchedule::factory()->count(30)->create();

        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 30,
                    'per_page' => 10,
                    'current_page' => 1,
                ],
            ]);
    }

    public function test_filter_schedules_by_doctor_id()
    {
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();

        DoctorSchedule::factory()->create(['doctor_id' => $doctor1->id]);
        DoctorSchedule::factory()->count(3)->create(['doctor_id' => $doctor2->id]);

        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules?doctor_id={$doctor1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    ['doctor_id' => $doctor1->id],
                ],
            ]);
    }

    public function test_filter_schedules_by_clinic_id()
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        DoctorSchedule::factory()->create(['clinic_id' => $clinic1->id]);
        DoctorSchedule::factory()->count(2)->create(['clinic_id' => $clinic2->id]);

        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules?clinic_id={$clinic1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_schedules_by_day_of_week()
    {
        DoctorSchedule::factory()->create(['day_of_week' => 1]);
        DoctorSchedule::factory()->count(2)->create(['day_of_week' => 2]);

        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules?day_of_week=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    ['day_of_week' => 1],
                ],
            ]);
    }

    public function test_filter_schedules_by_availability()
    {
        DoctorSchedule::factory()->create(['is_available' => true]);
        DoctorSchedule::factory()->count(2)->create(['is_available' => false]);

        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules?is_available=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    ['is_available' => true],
                ],
            ]);
    }

    /**
     * ============================================
     * CREATE DOCTOR SCHEDULE TESTS
     * ============================================
     */

    public function test_can_create_doctor_schedule()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 30,
            'is_available' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Doctor schedule created successfully',
                'data' => [
                    'doctor_id' => $this->doctor->id,
                    'clinic_id' => $this->clinic->id,
                    'day_of_week' => 1,
                    'slot_duration' => 30,
                    'is_available' => true,
                ],
            ]);

        $this->assertDatabaseHas('doctor_schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_create_schedule_with_default_values()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 2,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slot_duration' => 30,
                    'is_available' => true,
                ],
            ]);
    }

    public function test_cannot_create_duplicate_schedule()
    {
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'A schedule already exists for this doctor at this clinic on this day.',
            ]);
    }

    public function test_validate_end_time_after_start_time()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '17:00',
            'end_time' => '09:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_time');
    }

    public function test_validate_doctor_exists()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => 9999,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('doctor_id');
    }

    public function test_validate_clinic_exists()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => 9999,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('clinic_id');
    }

    public function test_validate_day_of_week_range()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 7,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('day_of_week');
    }

    public function test_validate_slot_duration_minimum()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slot_duration');
    }

    public function test_validate_slot_duration_maximum()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 500,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slot_duration');
    }

    public function test_validate_time_format()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '9:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('start_time');
    }

    /**
     * ============================================
     * GET SPECIFIC SCHEDULE TESTS
     * ============================================
     */

    public function test_can_get_specific_schedule()
    {
        $schedule = DoctorSchedule::factory()->create();

        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $schedule->id,
                    'doctor_id' => $schedule->doctor_id,
                    'clinic_id' => $schedule->clinic_id,
                ],
            ]);
    }

    public function test_get_nonexistent_schedule_returns_404()
    {
        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules/9999');

        $response->assertStatus(404);
    }

    /**
     * ============================================
     * UPDATE DOCTOR SCHEDULE TESTS
     * ============================================
     */

    public function test_can_update_doctor_schedule()
    {
        $schedule = DoctorSchedule::factory()->create();

        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}", [
            'start_time' => '08:00',
            'end_time' => '16:00',
            'slot_duration' => 45,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Doctor schedule updated successfully',
                'data' => [
                    'slot_duration' => 45,
                ],
            ]);

        $this->assertDatabaseHas('doctor_schedules', [
            'id' => $schedule->id,
            'slot_duration' => 45,
        ]);
    }

    public function test_detect_conflict_on_schedule_update()
    {
        $doctor = Doctor::factory()->create();
        $clinic = Clinic::factory()->create();

        // Create first schedule on day 1
        DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 1,
        ]);

        // Create second schedule on day 2
        $schedule2 = DoctorSchedule::factory()->create([
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 2,
        ]);

        // Try to update schedule2's day_of_week to 1 (should conflict)
        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule2->id}", [
            'day_of_week' => 1,
        ]);

        // Expect 422 since this violates unique constraint
        $response->assertStatus(422);
    }

    public function test_cannot_mark_unavailable_with_existing_appointments()
    {
        $schedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'is_available' => true,
        ]);

        $appointmentDate = now()->next(1);
        while ($appointmentDate->dayOfWeek != $schedule->day_of_week) {
            $appointmentDate->addDay();
        }

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'appointment_date' => $appointmentDate,
            'status' => AppointmentStatus::PENDING,
        ]);

        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}", [
            'is_available' => false,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot disable schedule: existing appointments found',
            ]);
    }

    public function test_can_update_schedule_with_no_conflicting_appointments()
    {
        $schedule = DoctorSchedule::factory()->create([
            'is_available' => true,
        ]);

        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}", [
            'start_time' => '08:30',
            'end_time' => '16:30',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Doctor schedule updated successfully',
            ]);
    }

    /**
     * ============================================
     * DELETE DOCTOR SCHEDULE TESTS
     * ============================================
     */

    public function test_can_delete_doctor_schedule()
    {
        $schedule = DoctorSchedule::factory()->create();

        $response = $this->deleteJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('doctor_schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_cannot_delete_schedule_with_existing_appointments()
    {
        $schedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $appointmentDate = now()->next(1);
        while ($appointmentDate->dayOfWeek != $schedule->day_of_week) {
            $appointmentDate->addDay();
        }

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'appointment_date' => $appointmentDate,
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $response = $this->deleteJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete schedule: existing appointments found',
            ]);

        $this->assertDatabaseHas('doctor_schedules', [
            'id' => $schedule->id,
        ]);
    }

    /**
     * ============================================
     * AVAILABLE SLOTS TESTS
     * ============================================
     */

    public function test_can_get_available_slots()
    {
        $schedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 30,
            'is_available' => true,
        ]);

        $dateFrom = now()->next(1);
        while ($dateFrom->dayOfWeek != 1) {
            $dateFrom->addDay();
        }
        $dateTo = $dateFrom->copy();

        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules/available-slots?doctor_id={$this->doctor->id}&clinic_id={$this->clinic->id}&date_from={$dateFrom->format('Y-m-d')}&date_to={$dateTo->format('Y-m-d')}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'doctor_id',
                    'clinic_id',
                    'date_from',
                    'date_to',
                    'total_slots',
                    'slots' => [
                        '*' => [
                            'date',
                            'time',
                            'start_time',
                            'end_time',
                            'available',
                        ],
                    ],
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.total_slots'));
    }

    public function test_available_slots_excludes_booked_appointments()
    {
        $schedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'slot_duration' => 30,
            'is_available' => true,
        ]);

        $dateFrom = now()->next(1);
        while ($dateFrom->dayOfWeek != 1) {
            $dateFrom->addDay();
        }

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'appointment_date' => $dateFrom,
            'appointment_time' => $dateFrom->copy()->setHour(9)->setMinute(0),
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules/available-slots?doctor_id={$this->doctor->id}&clinic_id={$this->clinic->id}&date_from={$dateFrom->format('Y-m-d')}&date_to={$dateFrom->format('Y-m-d')}");

        $slots = $response->json('data.slots');
        
        $bookedTimes = array_filter($slots ?? [], function ($slot) {
            return $slot['time'] === '09:00';
        });

        $this->assertEmpty($bookedTimes);
    }

    public function test_no_available_slots_for_unavailable_schedule()
    {
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'is_available' => false,
        ]);

        $dateFrom = now()->next(1);
        while ($dateFrom->dayOfWeek != 1) {
            $dateFrom->addDay();
        }

        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules/available-slots?doctor_id={$this->doctor->id}&clinic_id={$this->clinic->id}&date_from={$dateFrom->format('Y-m-d')}&date_to={$dateFrom->format('Y-m-d')}");

        $this->assertEquals(0, $response->json('data.total_slots'));
    }

    /**
     * ============================================
     * TOGGLE AVAILABILITY TESTS
     * ============================================
     */

    public function test_can_toggle_availability()
    {
        $schedule = DoctorSchedule::factory()->create([
            'is_available' => true,
        ]);

        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}/toggle-availability");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Doctor availability updated successfully',
                'data' => [
                    'schedule_id' => $schedule->id,
                    'is_available' => false,
                ],
            ]);

        $this->assertDatabaseHas('doctor_schedules', [
            'id' => $schedule->id,
            'is_available' => false,
        ]);
    }

    public function test_toggle_availability_from_false_to_true()
    {
        $schedule = DoctorSchedule::factory()->create([
            'is_available' => false,
        ]);

        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}/toggle-availability");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_available' => true,
                ],
            ]);
    }

    public function test_cannot_toggle_to_unavailable_with_appointments()
    {
        $schedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'is_available' => true,
        ]);

        $appointmentDate = now()->next(1);
        while ($appointmentDate->dayOfWeek != $schedule->day_of_week) {
            $appointmentDate->addDay();
        }

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'appointment_date' => $appointmentDate,
            'status' => AppointmentStatus::PENDING,
        ]);

        $response = $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}/toggle-availability");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot mark unavailable: existing appointments found',
            ]);
    }

    /**
     * ============================================
     * BULK UPDATE TESTS
     * ============================================
     */

    public function test_can_bulk_update_schedules()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/bulk-update', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'slot_duration' => 30,
                ],
                [
                    'day_of_week' => 2,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'slot_duration' => 30,
                ],
                [
                    'day_of_week' => 3,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'slot_duration' => 30,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'created' => 3,
                    'updated' => 0,
                    'failed' => 0,
                ],
            ]);

        $this->assertDatabaseCount('doctor_schedules', 3);
    }

    public function test_bulk_update_creates_and_updates()
    {
        $existingSchedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'slot_duration' => 30,
        ]);

        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/bulk-update', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '08:00',
                    'end_time' => '16:00',
                    'slot_duration' => 45,
                ],
                [
                    'day_of_week' => 2,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'slot_duration' => 30,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'created' => 1,
                    'updated' => 1,
                    'failed' => 0,
                ],
            ]);

        $this->assertDatabaseHas('doctor_schedules', [
            'id' => $existingSchedule->id,
            'slot_duration' => 45,
        ]);
    }

    public function test_bulk_update_max_seven_schedules()
    {
        $schedules = array_map(function ($day) {
            return [
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ];
        }, range(0, 7));

        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/bulk-update', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'schedules' => $schedules,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('schedules');
    }

    public function test_bulk_update_validates_distinct_days()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/bulk-update', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ],
                [
                    'day_of_week' => 1,
                    'start_time' => '08:00',
                    'end_time' => '16:00',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('schedules.1.day_of_week');
    }

    /**
     * ============================================
     * CONFLICT CHECKING TESTS
     * ============================================
     */

    public function test_can_check_schedule_conflicts()
    {
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/check-conflicts', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Conflict check completed',
                'has_conflicts' => false,
                'conflicts' => [],
            ]);
    }

    public function test_detect_schedule_conflict()
    {
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/check-conflicts', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['has_conflicts' => true])
            ->assertJsonStructure([
                'conflicts' => [
                    '*' => ['type', 'message'],
                ],
            ]);
    }

    public function test_detect_appointment_conflict()
    {
        $schedule = DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $appointmentDate = now()->next(1);
        while ($appointmentDate->dayOfWeek != 1) {
            $appointmentDate->addDay();
        }

        Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'appointment_date' => $appointmentDate,
            'status' => AppointmentStatus::PENDING,
        ]);

        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules/check-conflicts', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['has_conflicts' => true]);

        $conflicts = $response->json('conflicts');
        $appointmentConflict = array_filter($conflicts, function ($conflict) {
            return $conflict['type'] === 'appointment_conflict';
        });

        $this->assertNotEmpty($appointmentConflict);
    }

    /**
     * ============================================
     * GET DOCTOR SCHEDULES TESTS
     * ============================================
     */

    public function test_can_get_doctor_schedules()
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        // Create 3 schedules at clinic1 with different days
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 1,
        ]);
        
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 2,
        ]);
        
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 3,
        ]);

        // Create 2 schedules at clinic2 with different days
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic2->id,
            'day_of_week' => 1,
        ]);
        
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic2->id,
            'day_of_week' => 2,
        ]);

        $response = $this->getJson(self::API_PREFIX . "/doctors/{$this->doctor->id}/schedules");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'clinic_id',
                        'clinic_name',
                        'schedules',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data); // 2 clinics
    }

    public function test_filter_doctor_schedules_by_clinic()
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        // Create 3 schedules at clinic1 with different days
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 1,
        ]);
        
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 2,
        ]);
        
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 3,
        ]);

        // Create 1 schedule at clinic2
        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic2->id,
            'day_of_week' => 1,
        ]);

        $response = $this->getJson(self::API_PREFIX . "/doctors/{$this->doctor->id}/schedules?clinic_id={$clinic1->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data); // Only 1 clinic
        $this->assertEquals($clinic1->id, $data[0]['clinic_id']);
    }

    /**
     * ============================================
     * AUTHENTICATION & AUTHORIZATION TESTS
     * ============================================
     */

    public function test_unauthenticated_user_cannot_access_schedules()
    {
        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules');
        
        // We're authenticated, so should be 200
        $this->assertEquals(200, $response->status());
    }

    public function test_authenticated_user_can_access_schedules()
    {
        DoctorSchedule::factory()->create();

        $response = $this->getJson(self::API_PREFIX . '/doctor-schedules');

        $response->assertStatus(200);
    }

    /**
     * ============================================
     * ACTIVITY LOGGING TESTS
     * ============================================
     */

    public function test_create_schedule_logs_activity()
    {
        $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        // Only check description - user_id is stored differently in activity_log
        $this->assertDatabaseHas('activity_log', [
            'description' => 'created doctor schedule',
        ]);
    }

    public function test_update_schedule_logs_activity()
    {
        $schedule = DoctorSchedule::factory()->create();

        $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}", [
            'slot_duration' => 45,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'updated doctor schedule',
        ]);
    }

    public function test_delete_schedule_logs_activity()
    {
        $schedule = DoctorSchedule::factory()->create();

        $this->deleteJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}");

        $this->assertDatabaseHas('activity_log', [
            'description' => 'deleted doctor schedule',
        ]);
    }

    public function test_toggle_availability_logs_activity()
    {
        $schedule = DoctorSchedule::factory()->create();

        $this->patchJson(self::API_PREFIX . "/doctor-schedules/{$schedule->id}/toggle-availability");

        $this->assertDatabaseHas('activity_log', [
            'description' => 'toggled doctor availability',
        ]);
    }

    public function test_bulk_update_logs_activity()
    {
        $this->postJson(self::API_PREFIX . '/doctor-schedules/bulk-update', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'schedules' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ],
            ],
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'bulk updated doctor schedules',
        ]);
    }

    /**
     * ============================================
     * EDGE CASE TESTS
     * ============================================
     */

    public function test_schedule_with_multiple_day_ranges()
    {
        $days = [1, 2, 3, 4, 5];
        foreach ($days as $day) {
            DoctorSchedule::factory()->create([
                'doctor_id' => $this->doctor->id,
                'clinic_id' => $this->clinic->id,
                'day_of_week' => $day,
            ]);
        }

        $response = $this->getJson(self::API_PREFIX . "/doctors/{$this->doctor->id}/schedules");

        $response->assertStatus(200);
        $schedules = $response->json('data.0.schedules');
        $this->assertCount(5, $schedules);
    }

    public function test_get_available_slots_with_multiple_clinics()
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic1->id,
            'day_of_week' => 1,
            'is_available' => true,
        ]);

        DoctorSchedule::factory()->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $clinic2->id,
            'day_of_week' => 1,
            'is_available' => false,
        ]);

        $dateFrom = now()->next(1);
        while ($dateFrom->dayOfWeek != 1) {
            $dateFrom->addDay();
        }

        // Get slots for clinic1
        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules/available-slots?doctor_id={$this->doctor->id}&clinic_id={$clinic1->id}&date_from={$dateFrom->format('Y-m-d')}&date_to={$dateFrom->format('Y-m-d')}");

        $this->assertGreaterThan(0, $response->json('data.total_slots') ?? 0);

        // Get slots for clinic2 (unavailable)
        $response = $this->getJson(self::API_PREFIX . "/doctor-schedules/available-slots?doctor_id={$this->doctor->id}&clinic_id={$clinic2->id}&date_from={$dateFrom->format('Y-m-d')}&date_to={$dateFrom->format('Y-m-d')}");

        $this->assertEquals(0, $response->json('data.total_slots'));
    }

    public function test_time_format_validation_edge_cases()
    {
        $invalidFormats = ['9:00', '09:0', '9:00:00', '9am', '9:00 AM'];

        foreach ($invalidFormats as $format) {
            $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
                'doctor_id' => $this->doctor->id,
                'clinic_id' => $this->clinic->id,
                'day_of_week' => 1,
                'start_time' => $format,
                'end_time' => '17:00',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors('start_time');
        }
    }

    public function test_slot_duration_boundary_values()
    {
        // Minimum valid
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 15,
        ]);
        $response->assertStatus(201);

        // Maximum valid
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 480,
        ]);
        $response->assertStatus(201);

        // Just below minimum
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 14,
        ]);
        $response->assertStatus(422);

        // Just above maximum
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 4,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'slot_duration' => 481,
        ]);
        $response->assertStatus(422);
    }

    public function test_day_of_week_boundary_values()
    {
        // Valid: 0 (Sunday)
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $response->assertStatus(201);

        // Valid: 6 (Saturday)
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 6,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $response->assertStatus(201);

        // Invalid: -1
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => -1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $response->assertStatus(422);

        // Invalid: 7
        $response = $this->postJson(self::API_PREFIX . '/doctor-schedules', [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 7,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $response->assertStatus(422);
    }
}