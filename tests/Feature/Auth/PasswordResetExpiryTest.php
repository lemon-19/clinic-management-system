<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PasswordResetExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_token_expires_and_cannot_be_used(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        // Request a password reset
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            // Expire the token by updating created_at older than expiry
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            DB::table(config('auth.passwords.'.config('auth.defaults.passwords').'.table'))
                ->where('email', $user->email)
                ->update(['created_at' => Carbon::now()->subMinutes($expire + 10)]);

            // Attempt to use expired token
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response->assertSessionHasErrors('email');

            return true;
        });
    }
}
