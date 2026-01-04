<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'patient_id',
        'clinic_id',
        'doctor_id',
        'service_id',
        'appointment_type',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'patient_notes',
        'doctor_notes',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'appointment_time' => 'datetime',
            'status' => AppointmentStatus::class,
            'appointment_type' => AppointmentType::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function videoConference(): HasOne
    {
        return $this->hasOne(VideoConference::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(ClinicRating::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
            ->whereIn('status', [AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', AppointmentStatus::COMPLETED);
    }

    public function scopeForDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeForClinic($query, $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    /**
     * Check if a time slot is available for a doctor at a clinic.
     *
     * @param int $doctorId
     * @param int $clinicId
     * @param \Carbon\Carbon $appointmentStart
     * @param int|null $ignoreAppointmentId
     * @return array [bool, string]
     */
    public static function isSlotAvailable($doctorId, $clinicId, $appointmentStart, $ignoreAppointmentId = null)
    {
        $day = $appointmentStart->dayOfWeek;

        $schedule = \App\Models\DoctorSchedule::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $day)
            ->available()
            ->first();

        if (! $schedule) {
            return [false, 'Doctor is not available on the selected date.'];
        }

        if (! $schedule->start_time || ! $schedule->end_time) {
            return [false, 'Doctor schedule is not properly configured.'];
        }

        $slotDuration = $schedule->slot_duration ?? 30;

        // Build schedule start/end for the appointment date (use time-of-day)
        $scheduleStart = $appointmentStart->copy()->setTimeFromTimeString($schedule->start_time->format('H:i:s'));
        $scheduleEnd = $appointmentStart->copy()->setTimeFromTimeString($schedule->end_time->format('H:i:s'));

        $appointmentEnd = $appointmentStart->copy()->addMinutes($slotDuration);

        if ($appointmentStart->lt($scheduleStart) || $appointmentEnd->gt($scheduleEnd)) {
            return [false, 'Selected time is outside the doctor\'s schedule.'];
        }

        $existing = self::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_date', $appointmentStart->toDateString())
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value]);

        if ($ignoreAppointmentId) {
            $existing->where('id', '!=', $ignoreAppointmentId);
        }

        $existing = $existing->get();

        foreach ($existing as $e) {
            $eStart = Carbon::parse($e->appointment_time);
            $eEnd = $eStart->copy()->addMinutes($slotDuration);

            if ($eStart->lt($appointmentEnd) && $eEnd->gt($appointmentStart)) {
                return [false, 'Selected time overlaps another appointment.'];
            }
        }

        return [true, ''];
    }

    /**
     * Whether the appointment can transition to the given status
     */
    public function canTransition(AppointmentStatus $to): bool
    {
        $from = $this->status;

        // normalize to enum instance if stored as string
        if ($from && ! ($from instanceof AppointmentStatus)) {
            try {
                $from = AppointmentStatus::from($from);
            } catch (\ValueError $e) {
                $from = null;
            }
        }

        return match ($from) {
            AppointmentStatus::PENDING, null => in_array($to, [AppointmentStatus::CONFIRMED, AppointmentStatus::CANCELLED], true),
            AppointmentStatus::CONFIRMED => in_array($to, [AppointmentStatus::COMPLETED, AppointmentStatus::CANCELLED], true),
            default => false,
        };
    }

    /**
     * Attempt to transition status, returns [bool, message]
     */
    public function transitionTo(AppointmentStatus $to): array
    {
        if (! $this->canTransition($to)) {
            return [false, "Cannot transition appointment from {$this->status?->value} to {$to->value}."];
        }

        // Use a direct DB update to avoid accidental inserts when model state is unexpected
        if (! $this->getKey()) {
            return [false, 'Appointment primary key missing.'];
        }

        $updated = self::whereKey($this->getKey())->update(['status' => $to->value]);

        if (! $updated) {
            return [false, 'Failed to update appointment status.'];
        }

        // Refresh model
        $this->refresh();

        return [true, ''];
    }

    /**
     * Cancel multiple appointments
     *
     * @param array $appointmentIds
     * @param string|null $reason
     * @return array [count => int, failed => int, errors => array]
     */
    public static function bulkCancel(array $appointmentIds, ?string $reason = null): array
    {
        $result = [
            'count' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $appointments = self::whereIn('id', $appointmentIds)->get();

        foreach ($appointments as $appointment) {
            if ($appointment->canTransition(AppointmentStatus::CANCELLED)) {
                [$success, $msg] = $appointment->transitionTo(AppointmentStatus::CANCELLED);
                if ($success) {
                    if ($reason) {
                        $appointment->update(['cancellation_reason' => $reason]);
                    }
                    $result['count']++;
                } else {
                    $result['failed']++;
                    $result['errors'][$appointment->id] = $msg;
                }
            } else {
                $result['failed']++;
                $result['errors'][$appointment->id] = "Cannot cancel appointment in {$appointment->status->value} status";
            }
        }

        return $result;
    }

    /**
     * Find appointments that conflict with a date range for a doctor
     *
     * @param int $doctorId
     * @param int $clinicId
     * @param string $dateFrom (YYYY-MM-DD)
     * @param string $dateTo (YYYY-MM-DD)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findConflictingAppointments(int $doctorId, int $clinicId, string $dateFrom, string $dateTo)
    {
        return self::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_date', '>=', $dateFrom)
            ->whereDate('appointment_date', '<=', $dateTo)
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value])
            ->get();
    }

    /**
     * Bulk reschedule conflicting appointments to a new date/time
     *
     * @param int $doctorId
     * @param int $clinicId
     * @param string $dateFrom
     * @param string $dateTo
     * @param string $newDate
     * @param string $newTime
     * @return array [count => int, failed => int, errors => array]
     */
    public static function bulkRescheduleConflicts(int $doctorId, int $clinicId, string $dateFrom, string $dateTo, string $newDate, string $newTime): array
    {
        $result = [
            'count' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $appointments = self::findConflictingAppointments($doctorId, $clinicId, $dateFrom, $dateTo);

        foreach ($appointments as $appointment) {
            // Check if the new slot is available
            $appointmentStart = Carbon::parse("{$newDate} {$newTime}");
            [$available, $message] = self::isSlotAvailable($doctorId, $clinicId, $appointmentStart, $appointment->id);

            if ($available) {
                $updated = self::whereKey($appointment->getKey())->update([
                    'appointment_date' => $newDate,
                    'appointment_time' => $appointmentStart->toDateTimeString(),
                    'status' => AppointmentStatus::PENDING->value,
                ]);

                if ($updated) {
                    $appointment->refresh();
                    $result['count']++;
                } else {
                    $result['failed']++;
                    $result['errors'][$appointment->id] = 'Failed to update appointment';
                }
            } else {
                $result['failed']++;
                $result['errors'][$appointment->id] = $message;
            }
        }

        return $result;
    }
}
