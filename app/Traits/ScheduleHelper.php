<?php

namespace App\Traits;

use App\Models\DoctorSchedule;
use Carbon\Carbon;

trait ScheduleHelper
{
    /**
     * Get day name from day_of_week number
     */
    public static function getDayName(int $dayOfWeek): string
    {
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return $days[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Get day number from day name
     */
    public static function getDayNumber(string $dayName): ?int
    {
        $days = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        return $days[strtolower($dayName)] ?? null;
    }

    /**
     * Check if a date is a working day for a doctor
     */
    public function isWorkingDay(Carbon $date, int $doctorId, int $clinicId): bool
    {
        return DoctorSchedule::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_available', true)
            ->exists();
    }

    /**
     * Get working hours for a day
     */
    public function getWorkingHours(int $dayOfWeek, int $doctorId, int $clinicId): ?array
    {
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$schedule || !$schedule->start_time || !$schedule->end_time) {
            return null;
        }

        return [
            'start' => $schedule->start_time->format('H:i'),
            'end' => $schedule->end_time->format('H:i'),
            'duration' => $schedule->start_time->diffInMinutes($schedule->end_time),
        ];
    }
}