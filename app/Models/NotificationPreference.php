<?php
// ============================================
// app/Models/NotificationPreference.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appointment_confirmation',
        'appointment_reminder_24h',
        'appointment_reminder_1h',
        'appointment_completed',
        'medical_record_shared',
        'prescription_added',
        'test_results_ready',
        'clinic_announcements',
        'system_notifications',
        'email_enabled',
        'sms_enabled',
        'in_app_enabled',
        'reminder_24h_time',
        'reminder_1h_time',
    ];

    protected $casts = [
        'appointment_confirmation' => 'boolean',
        'appointment_reminder_24h' => 'boolean',
        'appointment_reminder_1h' => 'boolean',
        'appointment_completed' => 'boolean',
        'medical_record_shared' => 'boolean',
        'prescription_added' => 'boolean',
        'test_results_ready' => 'boolean',
        'clinic_announcements' => 'boolean',
        'system_notifications' => 'boolean',
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
