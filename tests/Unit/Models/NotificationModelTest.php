<?php
// ============================================
// tests/Unit/Models/NotificationModelTest.php
// ============================================

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test notification can be created
     */
    public function test_notification_can_be_created(): void
    {
        $notification = Notification::factory()->create();

        $this->assertNotNull($notification->id);
        $this->assertNotNull($notification->type);
        $this->assertNotNull($notification->title);
    }

    /**
     * Test notification belongs to user
     */
    public function test_notification_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();

        $this->assertTrue($notification->user()->is($user));
    }

    /**
     * Test unread scope
     */
    public function test_unread_scope(): void
    {
        Notification::factory(3)->unread()->create();
        Notification::factory(2)->read()->create();

        $unread = Notification::unread()->count();

        $this->assertEquals(3, $unread);
    }

    /**
     * Test read scope
     */
    public function test_read_scope(): void
    {
        Notification::factory(3)->unread()->create();
        Notification::factory(2)->read()->create();

        $read = Notification::read()->count();

        $this->assertEquals(2, $read);
    }

    /**
     * Test mark as read
     */
    public function test_mark_as_read(): void
    {
        $notification = Notification::factory()->unread()->create();

        $this->assertNull($notification->read_at);

        $notification->markAsRead();

        $this->assertNotNull($notification->refresh()->read_at);
    }

    /**
     * Test notification data is cast to array
     */
    public function test_notification_data_is_cast_to_array(): void
    {
        $notification = Notification::factory()->create([
            'data' => ['key' => 'value', 'nested' => ['item' => 'data']]
        ]);

        $this->assertIsArray($notification->data);
        $this->assertEquals('value', $notification->data['key']);
    }
}