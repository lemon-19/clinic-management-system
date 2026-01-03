<?php

// ============================================
// app/Models/UserHmo.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHmo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hmo_name',
        'member_id',
        'validity_date',
    ];

    protected function casts(): array
    {
        return [
            'validity_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}