<?php

// ============================================
// app/Models/Announcement.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'title',
        'content',
        'published_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'published_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(AnnouncementImage::class);
    }
}