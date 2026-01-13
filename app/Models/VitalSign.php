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

    /**
     * Calculate BMI (Body Mass Index)
     * Formula: weight (kg) / (height (m))^2
     */
    public static function calculateBMI($weight, $height)
    {
        if (!$weight || !$height || $weight <= 0 || $height <= 0) {
            return null;
        }

        $heightMeters = $height / 100;
        return round($weight / ($heightMeters ** 2), 2);
    }

    /**
     * Get BMI category based on calculated BMI
     */
    public function getBMICategory(): ?string
    {
        if (!$this->bmi) {
            return null;
        }

        return match (true) {
            $this->bmi < 18.5 => 'Underweight',
            $this->bmi >= 18.5 && $this->bmi < 25 => 'Normal weight',
            $this->bmi >= 25 && $this->bmi < 30 => 'Overweight',
            $this->bmi >= 30 => 'Obese',
            default => null,
        };
    }

    /**
     * Check if blood pressure is high
     */
    public function isHighBloodPressure(): bool
    {
        if (!$this->blood_pressure_systolic || !$this->blood_pressure_diastolic) {
            return false;
        }
        return $this->blood_pressure_systolic >= 140 || $this->blood_pressure_diastolic >= 90;
    }

    /**
     * Check if heart rate is abnormal
     */
    public function isAbnormalHeartRate(): bool
    {
        if (!$this->heart_rate) {
            return false;
        }
        return $this->heart_rate < 60 || $this->heart_rate > 100;
    }

    /**
     * Scope to filter vital signs by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope to order by recorded date (newest first)
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }
}