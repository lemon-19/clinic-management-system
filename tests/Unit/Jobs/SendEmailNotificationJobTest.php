<?php
// ============================================
// tests/Unit/Jobs/SendEmailNotificationJobTest.php
// ============================================

namespace Tests\Unit\Jobs;

use App\Jobs\SendEmailNotificationJob;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendEmailNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_success_log(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'mail-success@test.com']);

        $notification = Notification::factory()->for($user)->create();

        $job = new SendEmailNotificationJob(
            $user->email,
            'Subject OK',
            'emails.generic-notification',
            ['message' => 'ok', 'title' => 'Subject OK'],
            $notification->id
        );

        // Run synchronously
        $job->handle();

        $this->assertDatabaseHas('notification_logs', [
            'notification_id' => $notification->id,
            'status' => 'successful',
            'channel' => 'email',
        ]);
    }

    public function test_job_creates_failed_log_on_exception(): void
    {
        // Make Mail::send throw
        Mail::shouldReceive('send')->andThrow(new \Exception('SMTP down'));

        $user = User::factory()->create(['email' => 'mail-fail@test.com']);

        $notification = Notification::factory()->for($user)->create();

        $job = new SendEmailNotificationJob(
            $user->email,
            'Subject Fail',
            'emails.generic-notification',
            ['message' => 'fail', 'title' => 'Subject Fail'],
            $notification->id
        );

        try {
            $job->handle();
        } catch (\Exception $e) {
            // expected to bubble
        }

        $this->assertDatabaseHas('notification_logs', [
            'notification_id' => $notification->id,
            'status' => 'failed',
            'channel' => 'email',
        ]);
    }
}
