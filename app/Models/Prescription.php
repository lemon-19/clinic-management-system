<?php

// ============================================
// app/Models/Prescription.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\NotificationService;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'prescribed_date',
        'notes',
        'is_visible_to_patient',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_date' => 'date',
            'is_visible_to_patient' => 'boolean',
        ];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(PrescriptionMedication::class);
    }

    protected static function booted(): void
    {
        static::created(function ($prescription) {
            if ($prescription->is_visible_to_patient) {
                app(NotificationService::class)->sendPrescriptionAdded($prescription);
            }
        });

        static::updated(function ($prescription) {
            if ($prescription->isDirty('is_visible_to_patient') && $prescription->is_visible_to_patient) {
                app(NotificationService::class)->sendPrescriptionAdded($prescription);
            }
        });
    }
    
}