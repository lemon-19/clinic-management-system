<?php

// ============================================
// app/Models/Announcement.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\NotificationService;

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

    protected static function booted(): void
    {
        static::created(function ($announcement) {
            if ($announcement->is_active) {
                app(NotificationService::class)->sendClinicAnnouncement($announcement);
            }
        });

        static::updated(function ($announcement) {
            if ($announcement->isDirty('is_active') && $announcement->is_active) {
                app(NotificationService::class)->sendClinicAnnouncement($announcement);
            }
        });
    }
}