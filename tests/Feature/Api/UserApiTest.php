<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions used by UserController routes
        Permission::firstOrCreate(['name' => 'view_users']);
        Permission::firstOrCreate(['name' => 'create_users']);
    }

    protected function createAuthUserWithPermissions(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        return $user;
    }

    public function test_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $user = $this->createAuthUserWithPermissions(['view_users']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/v1/users');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_user(): void
    {
        $user = $this->createAuthUserWithPermissions(['create_users']);
        $token = $user->createToken('test-token')->plainTextToken;

        $payload = [
            'email' => 'api-user@example.test',
            'password' => 'password',
            'first_name' => 'Api',
            'last_name' => 'User',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['email' => 'api-user@example.test']);

        $this->assertDatabaseHas('users', ['email' => 'api-user@example.test']);
    }
}
