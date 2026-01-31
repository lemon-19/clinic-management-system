<?php
// ============================================
// tests/Feature/Commands/SendScheduledNotificationsTest.php
// ============================================

namespace Tests\Feature\Commands;

use App\Jobs\SendEmailNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Carbon\Carbon;

class SendScheduledNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_notifications_are_delivered_and_jobs_dispatched(): void
    {
        Bus::fake();

        $user = User::factory()->create(['email' => 'user@test.com']);
        $user->notificationPreference()->create([
            'email_enabled' => true,
            'in_app_enabled' => true,
        ]);

        $scheduled = Notification::create([
            'user_id' => $user->id,
            'type' => 'custom_notification',
            'title' => 'Scheduled',
            'message' => 'This is a scheduled notification',
            'scheduled_at' => Carbon::now()->subMinute(),
        ]);

        $this->artisan('notifications:send-scheduled')->assertExitCode(0);

        $this->assertNotNull($scheduled->refresh()->sent_at);

        Bus::assertDispatched(SendEmailNotificationJob::class, function ($job) use ($user) {
            return $job->email === $user->email;
        });
    }

    public function test_scheduled_notification_respects_preferences(): void
    {
        Bus::fake();

        $user = User::factory()->create(['email' => 'silent@test.com']);
        $user->notificationPreference()->create([
            'email_enabled' => false,
            'in_app_enabled' => false,
        ]);

        $scheduled = Notification::create([
            'user_id' => $user->id,
            'type' => 'custom_notification',
            'title' => 'Scheduled Silent',
            'message' => 'Should not notify via channels',
            'scheduled_at' => Carbon::now()->subMinute(),
        ]);

        $this->artisan('notifications:send-scheduled')->assertExitCode(0);

        $this->assertNotNull($scheduled->refresh()->sent_at);

        // No email job should be dispatched
        Bus::assertNotDispatched(SendEmailNotificationJob::class);

        // No extra notifications should be created besides the scheduled record
        $count = Notification::where('user_id', $user->id)->count();
        $this->assertEquals(1, $count);
    }
}
