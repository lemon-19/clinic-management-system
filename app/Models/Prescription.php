<?php

// ============================================
// app/Models/Prescription.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}