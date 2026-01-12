<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_get_me(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertStatus(200)->assertJsonStructure(['token','user']);

        $token = $response->json('token');

        $me = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me');

        $me->assertStatus(200)->assertJsonFragment(['email' => $user->email]);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $token = $user->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/logout');
        $resp->assertStatus(204);
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $payload = [
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'first_name' => 'New',
            'last_name' => 'User',
        ];

        $resp = $this->postJson('/api/v1/register', $payload);

        $resp->assertStatus(201)->assertJsonStructure(['token','user']);
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_tokens_list_and_revoke(): void
    {
        $user = User::factory()->create();
        $t1 = $user->createToken('t1')->plainTextToken;
        $t2 = $user->createToken('t2')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$t1)->getJson('/api/v1/tokens');
        $resp->assertStatus(200)->assertJsonCount(2);

        // Revoke second token
        $row = \Laravel\Sanctum\PersonalAccessToken::findToken($t2);
        $del = $this->withHeader('Authorization', 'Bearer '.$t1)->deleteJson('/api/v1/tokens/'.$row->id);
        $del->assertStatus(204);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $row->id]);
    }

    public function test_change_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $token = $user->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/me/password', [
            'current_password' => 'password', // ✅ must send current password
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $resp->assertStatus(200);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword', $user->fresh()->password));
    }

}
