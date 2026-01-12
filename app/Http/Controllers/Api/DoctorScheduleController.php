<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorScheduleRequest;
use App\Http\Requests\UpdateDoctorScheduleRequest;
use App\Http\Requests\BulkUpdateDoctorSchedulesRequest;
use App\Http\Requests\CheckScheduleConflictsRequest;
use App\Models\DoctorSchedule;
use App\Models\Doctor;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DoctorScheduleController extends Controller
{
    /**
     * List all doctor schedules with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = DoctorSchedule::with(['doctor.user', 'clinic']);

        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->query('doctor_id'));
        }

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->query('clinic_id'));
        }

        if ($request->has('day_of_week')) {
            $query->where('day_of_week', $request->query('day_of_week'));
        }

        if ($request->has('is_available')) {
            $isAvailable = filter_var($request->query('is_available'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_available', $isAvailable);
        }

        // Sort by doctor_id and day_of_week by default
        $query->orderBy('doctor_id')->orderBy('day_of_week');

        $schedules = $query->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => $schedules->items(),
            'meta' => [
                'total' => $schedules->total(),
                'per_page' => $schedules->perPage(),
                'current_page' => $schedules->currentPage(),
            ],
        ]);
    }

    /**
     * Create a new doctor schedule
     */
    public function store(StoreDoctorScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Check for conflicts with existing schedules
        $existingSchedule = DoctorSchedule::where('doctor_id', $data['doctor_id'])
            ->where('clinic_id', $data['clinic_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->first();

        if ($existingSchedule) {
            return response()->json([
                'message' => 'A schedule already exists for this doctor at this clinic on this day.',
                'errors' => ['schedule' => ['Schedule conflict detected']]
            ], 422);
        }

        $schedule = DoctorSchedule::create($data);

        if (function_exists('activity')) {
            activity()
                ->performedOn($schedule)
                ->causedBy($request->user())
                ->withProperties($schedule->toArray())
                ->log('created doctor schedule');
        }

        return response()->json([
            'message' => 'Doctor schedule created successfully',
            'data' => $schedule->load(['doctor.user', 'clinic']),
        ], 201);
    }

    /**
     * Get specific doctor schedule
     */
    public function show($scheduleId): JsonResponse
    {
        $schedule = DoctorSchedule::with(['doctor.user', 'clinic'])
            ->findOrFail($scheduleId);

        return response()->json([
            'data' => $schedule,
        ]);
    }

    /**
     * Update doctor schedule
     */
    public function update(UpdateDoctorScheduleRequest $request, $scheduleId): JsonResponse
    {
        $schedule = DoctorSchedule::findOrFail($scheduleId);
        $data = $request->validated();

        // Check for conflicts if doctor, clinic, or day_of_week are being changed
        if ((isset($data['doctor_id']) || isset($data['clinic_id']) || isset($data['day_of_week'])) &&
            (isset($data['doctor_id']) && $data['doctor_id'] != $schedule->doctor_id || 
             isset($data['clinic_id']) && $data['clinic_id'] != $schedule->clinic_id || 
             isset($data['day_of_week']) && $data['day_of_week'] != $schedule->day_of_week)) {

            $conflict = DoctorSchedule::where('doctor_id', $data['doctor_id'] ?? $schedule->doctor_id)
                ->where('clinic_id', $data['clinic_id'] ?? $schedule->clinic_id)
                ->where('day_of_week', $data['day_of_week'] ?? $schedule->day_of_week)
                ->where('id', '!=', $schedule->id)
                ->first();

            if ($conflict) {
                return response()->json([
                    'message' => 'Schedule conflict detected',
                    'errors' => ['schedule' => ['A schedule already exists for this configuration']]
                ], 422);
            }
        }

        // Check if there are existing appointments that would be affected
        if (isset($data['is_available']) && !$data['is_available']) {
            $conflictingAppointments = $this->getConflictingAppointments($schedule);
            if ($conflictingAppointments->count() > 0) {
                return response()->json([
                    'message' => 'Cannot disable schedule: existing appointments found',
                    'conflicting_appointments' => $conflictingAppointments->count(),
                    'data' => $conflictingAppointments->pluck('id')->toArray(),
                ], 422);
            }
        }

        // Wrap update in try-catch to handle unique constraint violations
        try {
            $schedule->update($data);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a unique constraint violation (error code 23000)
            if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                return response()->json([
                    'message' => 'Schedule conflict detected',
                ], 422);
            }
            // Re-throw if it's a different database error
            throw $e;
        }

        if (function_exists('activity')) {
            activity()
                ->performedOn($schedule)
                ->causedBy($request->user())
                ->withProperties($schedule->getChanges())
                ->log('updated doctor schedule');
        }

        return response()->json([
            'message' => 'Doctor schedule updated successfully',
            'data' => $schedule->load(['doctor.user', 'clinic']),
        ]);
    }

    /**
     * Delete doctor schedule
     */
    public function destroy(Request $request, $scheduleId): JsonResponse
    {
        $schedule = DoctorSchedule::findOrFail($scheduleId);

        // Check for existing appointments
        $existingAppointments = $this->getConflictingAppointments($schedule);
        if ($existingAppointments->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete schedule: existing appointments found',
                'conflicting_appointments' => $existingAppointments->count(),
            ], 422);
        }

        $schedule->delete();

        if (function_exists('activity')) {
            activity()
                ->performedOn($schedule)
                ->causedBy($request->user())
                ->withProperties(['schedule_id' => $schedule->id])
                ->log('deleted doctor schedule');
        }

        return response()->json(['message' => 'Doctor schedule deleted successfully'], 204);
    }

    /**
     * Get available time slots for a specific doctor at a clinic
     * Used by appointment booking to show available slots
     */
    public function getAvailableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'clinic_id' => ['required', 'exists:clinics,id'],
            'date_from' => ['required', 'date', 'after:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $doctorId = $request->input('doctor_id');
        $clinicId = $request->input('clinic_id');
        $dateFrom = Carbon::parse($request->input('date_from'));
        $dateTo = Carbon::parse($request->input('date_to'));

        $slots = [];
        $currentDate = $dateFrom->copy();

        while ($currentDate->lte($dateTo)) {
            $dayOfWeek = $currentDate->dayOfWeek;

            $schedule = DoctorSchedule::where('doctor_id', $doctorId)
                ->where('clinic_id', $clinicId)
                ->where('day_of_week', $dayOfWeek)
                ->available()
                ->first();

            if ($schedule && $schedule->start_time && $schedule->end_time) {
                $daySlots = $this->generateDaySlots($currentDate, $schedule);
                $slots = array_merge($slots, $daySlots);
            }

            $currentDate->addDay();
        }

        return response()->json([
            'message' => 'Available slots retrieved successfully',
            'data' => [
                'doctor_id' => $doctorId,
                'clinic_id' => $clinicId,
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'total_slots' => count($slots),
                'slots' => $slots,
            ],
        ]);
    }

    /**
     * Mark doctor as available/unavailable
     * Simplified endpoint to toggle availability
     */
    public function toggleAvailability(Request $request, $scheduleId): JsonResponse
    {
        $schedule = DoctorSchedule::findOrFail($scheduleId);

        // If marking as unavailable, check for conflicts
        if ($schedule->is_available) {
            $conflictingAppointments = $this->getConflictingAppointments($schedule);
            if ($conflictingAppointments->count() > 0) {
                return response()->json([
                    'message' => 'Cannot mark unavailable: existing appointments found',
                    'conflicting_appointments' => $conflictingAppointments->count(),
                ], 422);
            }
        }

        $schedule->update(['is_available' => !$schedule->is_available]);

        if (function_exists('activity')) {
            activity()
                ->performedOn($schedule)
                ->causedBy($request->user())
                ->withProperties(['is_available' => $schedule->is_available])
                ->log('toggled doctor availability');
        }

        return response()->json([
            'message' => 'Doctor availability updated successfully',
            'data' => [
                'schedule_id' => $schedule->id,
                'is_available' => $schedule->is_available,
            ],
        ]);
    }

    /**
     * Bulk update schedules for a doctor
     * Useful for setting up multiple schedules at once
     */
    public function bulkUpdate(BulkUpdateDoctorSchedulesRequest $request): JsonResponse
    {
        $data = $request->validated();
        $doctorId = $data['doctor_id'];
        $clinicId = $data['clinic_id'];
        $schedules = $data['schedules'];

        $result = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($schedules as $index => $scheduleData) {
                try {
                    $existingSchedule = DoctorSchedule::where('doctor_id', $doctorId)
                        ->where('clinic_id', $clinicId)
                        ->where('day_of_week', $scheduleData['day_of_week'])
                        ->first();

                    if ($existingSchedule) {
                        $existingSchedule->update($scheduleData);
                        $result['updated']++;
                    } else {
                        DoctorSchedule::create(array_merge($scheduleData, [
                            'doctor_id' => $doctorId,
                            'clinic_id' => $clinicId,
                        ]));
                        $result['created']++;
                    }
                } catch (\Exception $e) {
                    $result['failed']++;
                    $result['errors'][$index] = $e->getMessage();
                }
            }

            DB::commit();

            if (function_exists('activity')) {
                activity()
                    ->causedBy($request->user())
                    ->withProperties($data)
                    ->log('bulk updated doctor schedules');
            }

            return response()->json([
                'message' => "Successfully processed {$result['created']} new and {$result['updated']} updated schedules",
                'data' => $result,
            ], $result['failed'] > 0 ? 207 : 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk update schedules failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to bulk update schedules',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for scheduling conflicts
     * Validates if a proposed schedule conflicts with existing appointments
     */
    public function checkConflicts(CheckScheduleConflictsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $conflicts = $this->detectConflicts($data);

        return response()->json([
            'message' => 'Conflict check completed',
            'has_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Get schedules for a specific doctor
     * Grouped by clinic for easier viewing
     */
    public function getDoctorSchedules(Request $request, $doctorId): JsonResponse
    {
        $request->validate([
            'clinic_id' => ['nullable', 'exists:clinics,id'],
        ]);

        $query = DoctorSchedule::where('doctor_id', $doctorId)
            ->with(['clinic']);

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->query('clinic_id'));
        }

        $schedules = $query->orderBy('clinic_id')->orderBy('day_of_week')->get();

        // Group by clinic
        $grouped = $schedules->groupBy('clinic_id')->map(function ($clinicSchedules) {
            return [
                'clinic_id' => $clinicSchedules->first()->clinic_id,
                'clinic_name' => $clinicSchedules->first()->clinic->clinic_name,
                'schedules' => $clinicSchedules->values()->toArray(),
            ];
        })->values();

        return response()->json([
            'message' => 'Doctor schedules retrieved successfully',
            'data' => $grouped,
        ]);
    }

    /**
     * Helper: Get conflicting appointments for a schedule
     */
    private function getConflictingAppointments(DoctorSchedule $schedule)
    {
        return Appointment::where('doctor_id', $schedule->doctor_id)
            ->where('clinic_id', $schedule->clinic_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->filter(function ($appointment) use ($schedule) {
                return $appointment->appointment_date && 
                       $appointment->appointment_date->dayOfWeek == $schedule->day_of_week;
            });
    }

    /**
     * Helper: Generate available time slots for a day
     */
    private function generateDaySlots(Carbon $date, DoctorSchedule $schedule): array
    {
        $slots = [];
        $slotDuration = $schedule->slot_duration ?? 30;

        $startTime = Carbon::parse($schedule->start_time->format('H:i:s'))->setDateFrom($date);
        $endTime = Carbon::parse($schedule->end_time->format('H:i:s'))->setDateFrom($date);

        $currentSlot = $startTime->copy();

        while ($currentSlot->copy()->addMinutes($slotDuration)->lte($endTime)) {
            // Check if this slot is booked
            $isBooked = Appointment::where('doctor_id', $schedule->doctor_id)
                ->where('clinic_id', $schedule->clinic_id)
                ->whereDate('appointment_date', $date->toDateString())
                ->whereIn('status', ['pending', 'confirmed'])
                ->get()
                ->some(function ($appointment) use ($currentSlot, $slotDuration) {
                    $appointmentStart = Carbon::parse($appointment->appointment_time);
                    $appointmentEnd = $appointmentStart->copy()->addMinutes($slotDuration);
                    $slotEnd = $currentSlot->copy()->addMinutes($slotDuration);

                    return $appointmentStart->lt($slotEnd) && $appointmentEnd->gt($currentSlot);
                });

            if (!$isBooked) {
                $slots[] = [
                    'date' => $date->format('Y-m-d'),
                    'time' => $currentSlot->format('H:i'),
                    'start_time' => $currentSlot->toIso8601String(),
                    'end_time' => $currentSlot->copy()->addMinutes($slotDuration)->toIso8601String(),
                    'available' => true,
                ];
            }

            $currentSlot->addMinutes($slotDuration);
        }

        return $slots;
    }

    /**
     * Helper: Detect conflicts for a proposed schedule change
     */
    private function detectConflicts(array $data): array
    {
        $conflicts = [];

        if (isset($data['schedule_id'])) {
            $schedule = DoctorSchedule::find($data['schedule_id']);
            if (!$schedule) return $conflicts;
        } else {
            $schedule = null;
        }

        $doctorId = $data['doctor_id'] ?? $schedule?->doctor_id;
        $clinicId = $data['clinic_id'] ?? $schedule?->clinic_id;
        $dayOfWeek = $data['day_of_week'] ?? $schedule?->day_of_week;

        if (!$doctorId || !$clinicId || !isset($dayOfWeek)) {
            return $conflicts;
        }

        // Check for overlapping schedules on same day
        $existingSchedule = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->where('id', '!=', $schedule?->id)
            ->first();

        if ($existingSchedule) {
            $conflicts[] = [
                'type' => 'schedule_conflict',
                'message' => 'Schedule already exists for this day',
                'conflicting_schedule_id' => $existingSchedule->id,
            ];
        }

        // Check for appointments on this day
        $appointments = Appointment::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->filter(function ($appointment) use ($dayOfWeek) {
                return $appointment->appointment_date && 
                       $appointment->appointment_date->dayOfWeek == $dayOfWeek;
            });

        if ($appointments->count() > 0) {
            $conflicts[] = [
                'type' => 'appointment_conflict',
                'message' => "Found {$appointments->count()} appointments on this day",
                'appointment_count' => $appointments->count(),
                'appointment_ids' => $appointments->pluck('id')->toArray(),
            ];
        }

        return $conflicts;
    }
}