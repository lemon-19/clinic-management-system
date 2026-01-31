<?php
// ============================================
// app/Console/Kernel.php
// IMPORTANT: Update this file with scheduling configuration
// ============================================

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send appointment reminders every hour
        $schedule->command('appointments:send-reminders')
            ->hourly()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Appointment reminders sent successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Appointment reminders failed');
            });

        // Send scheduled notifications every minute
        $schedule->command('notifications:send-scheduled')
            ->everyMinute()
            ->withoutOverlapping();

        // Clean up old notifications daily at 2 AM
        $schedule->command('notifications:cleanup', ['--days' => 90])
            ->daily()
            ->at('02:00')
            ->withoutOverlapping();

        // Optional: Clean up old appointment logs
        $schedule->command('logs:clear')
            ->daily()
            ->at('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}