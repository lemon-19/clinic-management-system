<?php

// ============================================
// app/Models/VitalSign.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'recorded_at',
        'weight',
        'height',
        'bmi',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'temperature',
        'respiratory_rate',
        'oxygen_saturation',
        'blood_sugar',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'bmi' => 'decimal:2',
            'temperature' => 'decimal:1',
        ];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}