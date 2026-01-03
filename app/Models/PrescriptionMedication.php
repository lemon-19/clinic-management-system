<?php

// ============================================
// app/Models/PrescriptionMedication.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionMedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'medication_name',
        'dosage',
        'frequency',
        'duration',
        'instructions',
        'quantity',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}