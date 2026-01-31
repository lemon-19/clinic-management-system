<?php
// ============================================
// app/Console/Commands/NotificationsCleanup.php
// ============================================

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationsCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup {--days=90 : Number of days to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old notifications (default: 90 days old)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $days = (int) $this->option('days');
            $cutoffDate = Carbon::now()->subDays($days);

            $deletedCount = Notification::where('created_at', '<', $cutoffDate)->delete();

            $this->info("✓ Deleted {$deletedCount} old notification(s)");
            Log::info("Notifications cleanup completed: {$deletedCount} records deleted");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('NotificationsCleanup command failed', [
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }
}
