<?php
// ============================================
// app/Models/Notification.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications_custom';
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'data',
        'appointment_id', 'hours_before',
        'read_at', 'sent_at', 'scheduled_at',
    ];

    protected $casts = [
        'data' => 'array',
        'appointment_id' => 'integer',
        'hours_before' => 'integer',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }
}