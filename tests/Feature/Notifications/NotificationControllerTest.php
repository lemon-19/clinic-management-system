<?php
// ============================================
// tests/Feature/Notifications/NotificationControllerTest.php
// ============================================

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * Test get all notifications
     */
    public function test_can_get_all_notifications(): void
    {
        Notification::factory(5)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'message', 'read_at']
                ],
                'meta' => ['total', 'per_page', 'current_page', 'unread_count']
            ])
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test filter unread notifications
     */
    public function test_can_filter_unread_notifications(): void
    {
        Notification::factory(3)->unread()->create(['user_id' => $this->user->id]);
        Notification::factory(2)->read()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/notifications?filter=unread');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'meta' => ['unread_count' => 3]
            ]);
    }

    /**
     * Test filter read notifications
     */
    public function test_can_filter_read_notifications(): void
    {
        Notification::factory(3)->unread()->create(['user_id' => $this->user->id]);
        Notification::factory(2)->read()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/notifications?filter=read');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test mark notification as read
     */
    public function test_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->unread()->create(['user_id' => $this->user->id]);

        $response = $this->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        );

        $response->assertStatus(200);
        $this->assertNotNull($notification->refresh()->read_at);
    }

    /**
     * Test cannot mark other user's notification as read
     */
    public function test_cannot_mark_other_user_notification_as_read(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->unread()->create(['user_id' => $otherUser->id]);

        $response = $this->patchJson(
            "/api/v1/notifications/{$notification->id}/read"
        );

        $response->assertStatus(403);
    }

    /**
     * Test mark all as read
     */
    public function test_can_mark_all_as_read(): void
    {
        Notification::factory(5)->unread()->create(['user_id' => $this->user->id]);

        $response = $this->patchJson('/api/v1/notifications/read-all');

        $response->assertStatus(200);
        $unreadCount = Notification::where('user_id', $this->user->id)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test get unread count
     */
    public function test_can_get_unread_count(): void
    {
        Notification::factory(3)->unread()->create(['user_id' => $this->user->id]);
        Notification::factory(2)->read()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['unread_count' => 3]);
    }

    /**
     * Test get preferences
     */
    public function test_can_get_notification_preferences(): void
    {
        NotificationPreference::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/notifications/preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'appointment_confirmation',
                    'email_enabled',
                    'in_app_enabled'
                ]
            ]);
    }

    /**
     * Test update preferences
     */
    public function test_can_update_notification_preferences(): void
    {
        NotificationPreference::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson('/api/v1/notifications/preferences', [
            'appointment_reminder_24h' => false,
            'email_enabled' => false,
            'in_app_enabled' => true,
        ]);

        $response->assertStatus(200);
        $preference = $this->user->notificationPreference;
        $this->assertFalse($preference->appointment_reminder_24h);
        $this->assertFalse($preference->email_enabled);
        $this->assertTrue($preference->in_app_enabled);
    }

    /**
     * Test delete notification
     */
    public function test_can_delete_notification(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson(
            "/api/v1/notifications/{$notification->id}"
        );

        $response->assertStatus(204);
        $this->assertFalse(Notification::where('id', $notification->id)->exists());
    }

    /**
     * Test cannot delete other user's notification
     */
    public function test_cannot_delete_other_user_notification(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson(
            "/api/v1/notifications/{$notification->id}"
        );

        $response->assertStatus(403);
    }

    /**
     * Test pagination
     */
    public function test_notifications_are_paginated(): void
    {
        Notification::factory(30)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/notifications?per_page=15');

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJson([
                'meta' => [
                    'per_page' => 15,
                    'total' => 30
                ]
            ]);
    }

    /**
     * Test unauthenticated user cannot access notifications
     */
    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        // Log out the current user to simulate an unauthenticated request
        auth()->logout();

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }
}