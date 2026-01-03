<?php

// ============================================
// app/Models/MedicalDocument.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'type',
        'title',
        'content',
        'file_path',
        'issued_date',
        'is_visible_to_patient',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'is_visible_to_patient' => 'boolean',
        ];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }
}