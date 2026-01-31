<?php
// ============================================
// app/Services/NotificationService.php
// ============================================

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\Appointment;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct()
    {
    }

    /**
     * Send appointment confirmation
     */
    public function sendAppointmentConfirmation(Appointment $appointment): void
    {
        $patient = $appointment->patient;
        $doctor = $appointment->doctor;

        $patientPrefs = $patient->notificationPreference;
        if (!$patientPrefs?->appointment_confirmation) {
            return;
        }

        try {
            // Email to patient if enabled
            if ($patientPrefs?->email_enabled ?? true) {
                // Create a placeholder notification record so we can attach logs to it
                $notification = $this->createInAppNotificationIfAllowed(
                    $patient,
                    'appointment_confirmation',
                    'Appointment Confirmed',
                    "Your appointment with {$doctor->user->full_name} is confirmed for {$appointment->appointment_date->format('F d, Y')} at {$appointment->appointment_time->format('h:i A')}",
                    ['appointment_id' => $appointment->id]
                );

                $notificationId = $notification?->id ?? null;

                // Dispatch email via queue
                \App\Jobs\SendEmailNotificationJob::dispatch(
                    $patient->email,
                    'Appointment Confirmed',
                    'emails.appointment-confirmation',
                    [
                        'patient' => $patient,
                        'appointment' => $appointment,
                        'doctor' => $doctor,
                    ],
                    $notificationId
                );
            } else {
                // Still create in-app if allowed
                $this->createInAppNotificationIfAllowed(
                    $patient,
                    'appointment_confirmation',
                    'Appointment Confirmed',
                    "Your appointment with {$doctor->user->full_name} is confirmed for {$appointment->appointment_date->format('F d, Y')} at {$appointment->appointment_time->format('h:i A')}",
                    ['appointment_id' => $appointment->id]
                );
            }

            // Doctor notifications
            if ($doctor && $doctor->user) {
                $doctorPrefs = $doctor->user->notificationPreference;
                if ($doctorPrefs?->email_enabled ?? true) {
                    $notification = $this->createInAppNotificationIfAllowed(
                        $doctor->user,
                        'appointment_confirmation',
                        'New Appointment Scheduled',
                        "New appointment with {$patient->full_name} on {$appointment->appointment_date->format('F d, Y')} at {$appointment->appointment_time->format('h:i A')}",
                        ['appointment_id' => $appointment->id]
                    );

                    \App\Jobs\SendEmailNotificationJob::dispatch(
                        $doctor->user->email,
                        'New Appointment Scheduled',
                        'emails.appointment-confirmation-doctor',
                        [
                            'doctor' => $doctor,
                            'appointment' => $appointment,
                            'patient' => $patient,
                        ],
                        $notification?->id ?? null
                    );
                } else {
                    $this->createInAppNotificationIfAllowed(
                        $doctor->user,
                        'appointment_confirmation',
                        'New Appointment Scheduled',
                        "New appointment with {$patient->full_name} on {$appointment->appointment_date->format('F d, Y')} at {$appointment->appointment_time->format('h:i A')}",
                        ['appointment_id' => $appointment->id]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Appointment confirmation failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send appointment reminder
     */
    public function sendAppointmentReminder(Appointment $appointment, int $hoursBefore = 24): void
    {
        $patient = $appointment->patient;
        $doctor = $appointment->doctor;

        $patientPrefs = $patient->notificationPreference;
        $shouldRemind = $hoursBefore === 24
            ? $patientPrefs?->appointment_reminder_24h
            : $patientPrefs?->appointment_reminder_1h;

        if (!$shouldRemind) {
            return;
        }

        // Idempotency: skip if a reminder for this appointment and timeframe already exists
        $existingNotifications = Notification::where('user_id', $patient->id)
            ->where('type', 'appointment_reminder')
            ->get();

        foreach ($existingNotifications as $ex) {
            $data = $ex->data ?? [];
            if (isset($data['appointment_id'], $data['hours_before']) && $data['appointment_id'] == $appointment->id && $data['hours_before'] == $hoursBefore) {
                return;
            }
        }

        try {
            $reminderText = $hoursBefore === 24 ? 'tomorrow' : 'in 1 hour';

            $canInApp = $patientPrefs?->in_app_enabled ?? true;
            $canEmail = $patientPrefs?->email_enabled ?? true;

            if (! $canInApp && ! $canEmail) {
                return; // nothing to do
            }

            // Create a single notification record representing this reminder
            $notification = Notification::create([
                'user_id' => $patient->id,
                'type' => 'appointment_reminder',
                'title' => "Appointment Reminder - {$reminderText}",
                'message' => "Reminder: Your appointment with {$doctor->user->full_name} is {$reminderText}",
                'data' => ['appointment_id' => $appointment->id, 'hours_before' => $hoursBefore],
                'sent_at' => now(),
            ]);

            // If in-app is enabled we don't need to create a second record (the record above is the in-app item)
            // If email is enabled dispatch a queued job and attach logs via NotificationLog inside the job
            if ($canEmail) {
                \App\Jobs\SendEmailNotificationJob::dispatch(
                    $patient->email,
                    "Appointment Reminder - {$reminderText}",
                    'emails.appointment-reminder',
                    [
                        'patient' => $patient,
                        'appointment' => $appointment,
                        'doctor' => $doctor,
                        'hours_before' => $hoursBefore,
                    ],
                    $notification->id
                );
            }

            // Doctor side
            if ($doctor && $doctor->user) {
                $doctorPrefs = $doctor->user->notificationPreference;

                // Idempotency for doctor
                $doctorExisting = Notification::where('user_id', $doctor->user->id)
                    ->where('type', 'appointment_reminder')
                    ->get();

                $doctorExists = false;
                foreach ($doctorExisting as $ex) {
                    $d = $ex->data ?? [];
                    if (isset($d['appointment_id'], $d['hours_before']) && $d['appointment_id'] == $appointment->id && $d['hours_before'] == $hoursBefore) {
                        $doctorExists = true;
                        break;
                    }
                }

                if (! $doctorExists) {
                    $doctorCanInApp = $doctorPrefs?->in_app_enabled ?? true;
                    $doctorCanEmail = $doctorPrefs?->email_enabled ?? true;

                    if ($doctorCanInApp || $doctorCanEmail) {
                        $doctorNotification = Notification::create([
                            'user_id' => $doctor->user->id,
                            'type' => 'appointment_reminder',
                            'title' => "Upcoming Appointment - {$reminderText}",
                            'message' => "Reminder: Your appointment with {$patient->full_name} is {$reminderText}",
                            'data' => ['appointment_id' => $appointment->id, 'hours_before' => $hoursBefore],
                            'sent_at' => now(),
                        ]);

                        if ($doctorCanEmail) {
                            \App\Jobs\SendEmailNotificationJob::dispatch(
                                $doctor->user->email,
                                "Upcoming Appointment - {$reminderText}",
                                'emails.appointment-reminder-doctor',
                                [
                                    'doctor' => $doctor,
                                    'appointment' => $appointment,
                                    'patient' => $patient,
                                    'hours_before' => $hoursBefore,
                                ],
                                $doctorNotification->id
                            );
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Appointment reminder failed', [
                'appointment_id' => $appointment->id,
                'hours_before' => $hoursBefore,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send medical record shared notification
     */
    public function sendMedicalRecordShared($medicalRecord): void
    {
        $patient = $medicalRecord->patient;
        $prefs = $patient->notificationPreference;

        if (!$prefs?->medical_record_shared) {
            return;
        }

        try {
            $this->createInAppNotificationIfAllowed(
                $patient,
                'medical_record_shared',
                'Medical Record Available',
                "Your medical record from {$medicalRecord->visit_date->format('F d, Y')} is now available",
                ['medical_record_id' => $medicalRecord->id]
            );

            if ($prefs?->email_enabled ?? true) {
                $notification = Notification::create([
                    'user_id' => $patient->id,
                    'type' => 'medical_record_shared',
                    'title' => 'Your Medical Record is Available',
                    'message' => "Your medical record from {$medicalRecord->visit_date->format('F d, Y')} is now available",
                    'data' => ['medical_record_id' => $medicalRecord->id],
                    'sent_at' => now(),
                ]);

                \App\Jobs\SendEmailNotificationJob::dispatch(
                    $patient->email,
                    'Your Medical Record is Available',
                    'emails.medical-record-shared',
                    ['patient' => $patient, 'medicalRecord' => $medicalRecord],
                    $notification->id
                );
            }
        } catch (\Exception $e) {
            Log::error('Medical record notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send prescription added notification
     */
    public function sendPrescriptionAdded($prescription): void
    {
        $medicalRecord = $prescription->medicalRecord;
        $patient = $medicalRecord->patient;
        $prefs = $patient->notificationPreference;

        if (!$prefs?->prescription_added) {
            return;
        }

        try {
            $this->createInAppNotificationIfAllowed(
                $patient,
                'prescription_added',
                'New Prescription',
                "A new prescription with {$prescription->medications->count()} medication(s) is ready",
                ['prescription_id' => $prescription->id]
            );

            if ($prefs?->email_enabled ?? true) {
                $notification = Notification::create([
                    'user_id' => $patient->id,
                    'type' => 'prescription_added',
                    'title' => 'New Prescription',
                    'message' => "A new prescription with {$prescription->medications->count()} medication(s) is ready",
                    'data' => ['prescription_id' => $prescription->id],
                    'sent_at' => now(),
                ]);

                \App\Jobs\SendEmailNotificationJob::dispatch(
                    $patient->email,
                    'New Prescription Available',
                    'emails.prescription-added',
                    ['patient' => $patient, 'prescription' => $prescription],
                    $notification->id
                );
            }
        } catch (\Exception $e) {
            Log::error('Prescription notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send clinic announcement
     */
    public function sendClinicAnnouncement($announcement): void
    {
        $clinic = $announcement->clinic;

        // Use chunking to avoid memory spikes
        \App\Models\User::where('status', 'active')
            ->chunkById(200, function ($users) use ($announcement) {
                foreach ($users as $user) {
                    $prefs = $user->notificationPreference;
                    if (!$prefs?->clinic_announcements) {
                        continue;
                    }

                    try {
                        $this->createInAppNotificationIfAllowed(
                            $user,
                            'clinic_announcement',
                            $announcement->title,
                            substr($announcement->content, 0, 150) . '...',
                            ['announcement_id' => $announcement->id]
                        );
                    } catch (\Exception $e) {
                        Log::error('Announcement notification failed', ['error' => $e->getMessage()]);
                    }
                }
            });
    }

    /**
     * Deliver a scheduled notification (called from a scheduled command)
     */
    public function deliverScheduledNotification(Notification $notification): void
    {
        $user = $notification->user;
        if (! $user) {
            return;
        }

        $prefs = $user->notificationPreference;

        // In-app
        if ($prefs?->in_app_enabled ?? true) {
            // Avoid duplicate in-app entries
            $exists = Notification::where('user_id', $user->id)
                ->where('type', $notification->type)
                ->where('id', $notification->id)
                ->exists();

            if (! $exists) {
                $this->createInAppNotificationIfAllowed(
                    $user,
                    $notification->type,
                    $notification->title,
                    $notification->message,
                    $notification->data ?? []
                );
            }
        }

        // Email
        if ($prefs?->email_enabled ?? true) {
            try {
                // Use a simple raw email when there is no template
                \App\Jobs\SendEmailNotificationJob::dispatch(
                    $user->email,
                    $notification->title ?? 'Notification',
                    'emails.generic-notification',
                    ['message' => $notification->message, 'title' => $notification->title],
                    $notification->id
                );
            } catch (\Exception $e) {
                Log::error('Scheduled notification email dispatch failed', ['error' => $e->getMessage(), 'notification_id' => $notification->id]);
            }
        }

        // Mark as sent
        $notification->update(['sent_at' => now()]);
    }

    /**
     * Create in-app notification if user preference allows it (defaulting to true)
     */
    public function createInAppNotificationIfAllowed($userOrId, string $type, string $title, string $message, array $data = []): ?Notification
    {
        $user = is_int($userOrId) ? \App\Models\User::find($userOrId) : $userOrId;
        if (! $user) {
            return null;
        }

        $prefs = $user->notificationPreference;
        if (! ($prefs?->in_app_enabled ?? true)) {
            return null;
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'sent_at' => now(),
        ]);
    }

    /**
     * Create in-app notification (force create, bypassing preferences)
     * Kept for backwards compatibility with tests and callers that expect explicit creation
     */
    public function createInAppNotification(int $userId, string $type, string $title, string $message, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): void
    {
        $notification = Notification::findOrFail($notificationId);
        $notification->markAsRead();
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead(int $userId): void
    {
        Notification::where('user_id', $userId)->update(['read_at' => now()]);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)->unread()->count();
    }

    /**
     * Get or create notification preference
     */
    public function getOrCreatePreference(int $userId): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $userId],
            [
                'appointment_confirmation' => true,
                'appointment_reminder_24h' => true,
                'appointment_reminder_1h' => true,
                'appointment_completed' => true,
                'medical_record_shared' => true,
                'prescription_added' => true,
                'test_results_ready' => true,
                'clinic_announcements' => true,
                'system_notifications' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'reminder_24h_time' => '09:00',
                'reminder_1h_time' => null,
            ]
        );
    }
}