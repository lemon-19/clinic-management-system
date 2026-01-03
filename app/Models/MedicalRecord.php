<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalRecord extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'patient_id',
        'clinic_id',
        'doctor_id',
        'visit_date',
        'chief_complaint',
        'diagnosis',
        'treatment_plan',
        'notes',
        'is_visible_to_patient',
        'allergies',
        'medications',
        'medical_history',
        'family_history',
        'social_history',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'is_visible_to_patient' => 'boolean',
            'allergies' => 'array',
            'medications' => 'array',
            'medical_history' => 'array',
            'family_history' => 'array',
            'social_history' => 'array',
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

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class);
    }
}