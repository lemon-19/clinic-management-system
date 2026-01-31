<?php
// ============================================
// tests/Unit/Models/NotificationPreferenceTest.php
// ============================================

namespace Tests\Unit\Models;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test preference can be created
     */
    public function test_preference_can_be_created(): void
    {
        $preference = NotificationPreference::factory()->create();

        $this->assertNotNull($preference->id);
        $this->assertNotNull($preference->user_id);
    }

    /**
     * Test preference belongs to user
     */
    public function test_preference_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $preference = NotificationPreference::factory()->for($user)->create();

        $this->assertTrue($preference->user()->is($user));
    }

    /**
     * Test boolean casting
     */
    public function test_boolean_fields_are_cast(): void
    {
        $preference = NotificationPreference::factory()->create([
            'email_enabled' => true,
            'sms_enabled' => false,
        ]);

        $this->assertTrue($preference->email_enabled);
        $this->assertFalse($preference->sms_enabled);
    }

    /**
     * Test default values
     */
    public function test_default_values_are_set(): void
    {
        $user = User::factory()->create();
        $preference = NotificationPreference::factory()->for($user)->create();

        $this->assertTrue($preference->appointment_confirmation);
        $this->assertTrue($preference->appointment_reminder_24h);
        $this->assertTrue($preference->email_enabled);
        $this->assertTrue($preference->in_app_enabled);
    }

    /**
     * Test email only preference
     */
    public function test_can_set_email_only_preferences(): void
    {
        $preference = NotificationPreference::factory()->emailOnly()->create();

        $this->assertTrue($preference->email_enabled);
        $this->assertFalse($preference->sms_enabled);
        $this->assertFalse($preference->in_app_enabled);
    }

    /**
     * Test in-app only preference
     */
    public function test_can_set_inapp_only_preferences(): void
    {
        $preference = NotificationPreference::factory()->inAppOnly()->create();

        $this->assertFalse($preference->email_enabled);
        $this->assertFalse($preference->sms_enabled);
        $this->assertTrue($preference->in_app_enabled);
    }

    /**
     * Test all channels disabled preference
     */
    public function test_can_disable_all_channels(): void
    {
        $preference = NotificationPreference::factory()->allChannelsDisabled()->create();

        $this->assertFalse($preference->email_enabled);
        $this->assertFalse($preference->sms_enabled);
        $this->assertFalse($preference->in_app_enabled);
    }
}
