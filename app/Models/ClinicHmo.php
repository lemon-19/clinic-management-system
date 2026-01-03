<?php

// ============================================
// app/Models/ClinicHmo.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicHmo extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'hmo_name',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}