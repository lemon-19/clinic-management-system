<?php
// ============================================
// app/Console/Commands/SendScheduledNotifications.php
// ============================================

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled notifications that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $now = Carbon::now();

            // Get all scheduled notifications that are due
            $notifications = Notification::whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', $now)
                ->whereNull('sent_at')
                ->with('user')
                ->get();

            $count = 0;
            foreach ($notifications as $notification) {
                try {
                    // Deliver using NotificationService so preferences and channels are respected
                    $service = app(\App\Services\NotificationService::class);
                    $service->deliverScheduledNotification($notification);
                    $count++;
                } catch (\Exception $e) {
                    Log::error("Failed to send scheduled notification {$notification->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("✓ Sent {$count} scheduled notification(s)");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('SendScheduledNotifications command failed', [
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }
}