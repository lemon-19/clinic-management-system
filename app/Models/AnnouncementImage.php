<?php

// ============================================
// app/Models/AnnouncementImage.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'image_path',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}