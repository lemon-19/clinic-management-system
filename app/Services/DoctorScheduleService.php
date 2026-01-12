<?php

namespace App\Services;

use App\Models\DoctorSchedule;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DoctorScheduleService
{
    /**
     * Get available time slots for a date range
     * Useful for appointment booking interfaces
     */
    public function getAvailableSlots(
        int $doctorId,
        int $clinicId,
        Carbon $dateFrom,
        Carbon $dateTo
    ): array {
        $slots = [];
        $currentDate = $dateFrom->copy();

        while ($currentDate->lte($dateTo)) {
            $dayOfWeek = $currentDate->dayOfWeek;

            $schedule = DoctorSchedule::where('doctor_id', $doctorId)
                ->where('clinic_id', $clinicId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_available', true)
                ->first();

            if ($schedule && $schedule->start_time && $schedule->end_time) {
                $daySlots = $this->generateDaySlots($currentDate, $schedule);
                $slots = array_merge($slots, $daySlots);
            }

            $currentDate->addDay();
        }

        return $slots;
    }

    /**
     * Check if a specific date-time is available
     */
    public function isTimeSlotAvailable(
        int $doctorId,
        int $clinicId,
        Carbon $appointmentTime,
        int $slotDuration = 30,
        ?int $ignoreAppointmentId = null
    ): bool {
        $dayOfWeek = $appointmentTime->dayOfWeek;

        $schedule = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return false;
        }

        // Check if time is within schedule
        $scheduleStart = Carbon::parse($schedule->start_time->format('H:i:s'))
            ->setDateFrom($appointmentTime);
        $scheduleEnd = Carbon::parse($schedule->end_time->format('H:i:s'))
            ->setDateFrom($appointmentTime);

        $appointmentEnd = $appointmentTime->copy()->addMinutes($slotDuration);

        if ($appointmentTime->lt($scheduleStart) || $appointmentEnd->gt($scheduleEnd)) {
            return false;
        }

        // Check for conflicts with existing appointments
        $conflictingAppointments = Appointment::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_date', $appointmentTime->toDateString())
            ->whereIn('status', ['pending', 'confirmed']);

        if ($ignoreAppointmentId) {
            $conflictingAppointments->where('id', '!=', $ignoreAppointmentId);
        }

        $conflictingAppointments = $conflictingAppointments->get();

        foreach ($conflictingAppointments as $appointment) {
            $existingStart = Carbon::parse($appointment->appointment_time);
            $existingEnd = $existingStart->copy()->addMinutes($slotDuration);

            if ($existingStart->lt($appointmentEnd) && $existingEnd->gt($appointmentTime)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all conflicting appointments for a schedule
     */
    public function getConflictingAppointments(DoctorSchedule $schedule): Collection
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
     * Calculate utilization rate for a schedule
     */
    public function getUtilizationRate(DoctorSchedule $schedule): float
    {
        if (!$schedule->start_time || !$schedule->end_time) {
            return 0;
        }

        $startTime = Carbon::parse($schedule->start_time);
        $endTime = Carbon::parse($schedule->end_time);
        $totalMinutes = $endTime->diffInMinutes($startTime);
        $slotDuration = $schedule->slot_duration ?? 30;
        $totalSlots = floor($totalMinutes / $slotDuration);

        if ($totalSlots === 0) {
            return 0;
        }

        $bookedSlots = Appointment::where('doctor_id', $schedule->doctor_id)
            ->where('clinic_id', $schedule->clinic_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->filter(function ($appointment) use ($schedule) {
                return $appointment->appointment_date &&
                       $appointment->appointment_date->dayOfWeek == $schedule->day_of_week;
            })
            ->count();

        return ($bookedSlots / $totalSlots) * 100;
    }

    /**
     * Generate time slots for a specific day
     */
    private function generateDaySlots(Carbon $date, DoctorSchedule $schedule): array
    {
        $slots = [];
        $slotDuration = $schedule->slot_duration ?? 30;

        $startTime = Carbon::parse($schedule->start_time->format('H:i:s'))
            ->setDateFrom($date);
        $endTime = Carbon::parse($schedule->end_time->format('H:i:s'))
            ->setDateFrom($date);

        $currentSlot = $startTime->copy();

        while ($currentSlot->copy()->addMinutes($slotDuration)->lte($endTime)) {
            // Check if booked
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
                ];
            }

            $currentSlot->addMinutes($slotDuration);
        }

        return $slots;
    }
}