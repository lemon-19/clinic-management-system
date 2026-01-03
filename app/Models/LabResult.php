<?php

// ============================================
// app/Models/LabResult.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'test_name',
        'test_date',
        'result',
        'notes',
        'file_path',
        'is_visible_to_patient',
    ];

    protected function casts(): array
    {
        return [
            'test_date' => 'date',
            'is_visible_to_patient' => 'boolean',
        ];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }
}