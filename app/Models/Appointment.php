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
}