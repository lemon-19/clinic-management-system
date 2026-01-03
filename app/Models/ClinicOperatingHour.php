<?php

// ============================================
// app/Models/ClinicOperatingHour.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicOperatingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'open_time' => 'datetime',
            'close_time' => 'datetime',
            'is_closed' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}