<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_user(): void
    {
        $payload = [
            'email' => 'api-user@example.test',
            'password' => 'password',
            'first_name' => 'Api',
            'last_name' => 'User',
        ];

        $response = $this->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)->assertJsonFragment(['email' => 'api-user@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'api-user@example.test']);
    }
}
