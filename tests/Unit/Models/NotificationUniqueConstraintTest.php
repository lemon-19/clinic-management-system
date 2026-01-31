<?php
// ============================================
// tests/Unit/Models/NotificationUniqueConstraintTest.php
// ============================================

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_reminder_is_blocked_by_db(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'type' => 'appointment_reminder',
            'title' => 'Reminder',
            'message' => 'Reminder 1',
            'data' => ['appointment_id' => 1, 'hours_before' => 24],
            'appointment_id' => 1,
            'hours_before' => 24,
            'sent_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'appointment_reminder',
            'title' => 'Reminder duplicated',
            'message' => 'Reminder 2',
            'data' => ['appointment_id' => 1, 'hours_before' => 24],
            'appointment_id' => 1,
            'hours_before' => 24,
            'sent_at' => now(),
        ]);
    }
}
