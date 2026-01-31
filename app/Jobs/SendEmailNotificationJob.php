<?php
// ============================================
// app/Jobs/SendEmailNotificationJob.php
// ============================================

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $subject,
        public string $template,
        public array $data,
        public ?int $notificationId = null
    ) {
    }

    public function handle(): void
    {
        try {
            // Use Mail::send with view template (existing templates use blade views)
            // Use Mailable and queue it for better structure
            \Illuminate\Support\Facades\Mail::to($this->email)
                ->queue(new \App\Mail\GenericNotificationMail($this->subject, $this->template, $this->data));

            // Resolve user id for the log entry
            $userId = null;
            if ($this->notificationId) {
                $notif = \App\Models\Notification::find($this->notificationId);
                $userId = $notif?->user_id ?? null;
            }

            if (! $userId) {
                $user = \App\Models\User::where('email', $this->email)->first();
                $userId = $user?->id ?? null;
            }

            if ($this->notificationId && $userId) {
                NotificationLog::create([
                    'user_id' => $userId,
                    'notification_id' => $this->notificationId,
                    'channel' => 'email',
                    'status' => 'successful',
                    'sent_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Ensure we have the user id if possible
            $userId = null;
            if ($this->notificationId) {
                $notif = \App\Models\Notification::find($this->notificationId);
                $userId = $notif?->user_id ?? null;
            }

            if (! $userId) {
                $user = \App\Models\User::where('email', $this->email)->first();
                $userId = $user?->id ?? null;
            }

            if ($this->notificationId && $userId) {
                NotificationLog::create([
                    'user_id' => $userId,
                    'notification_id' => $this->notificationId,
                    'channel' => 'email',
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            // Let the exception bubble so the job can be retried by the queue
            throw $e;
        }
    }
}
