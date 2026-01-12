<?php

namespace Tests\Feature\Api;

use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Permission;

class ClinicApiTest extends TestCase
{
    use RefreshDatabase;

        public function setUp(): void
    {
        parent::setUp();

        // Seed necessary permissions
        Permission::create(['name' => 'view_clinics']);
        Permission::create(['name' => 'create_clinic']);
    }

    public function test_can_list_clinics(): void
    {
        Clinic::factory()->count(3)->create();

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('view_clinics');
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
                         ->getJson('/api/v1/clinics');

        $response->assertStatus(200)->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_clinic(): void
    {
        $payload = [
            'clinic_name' => 'Test Clinic',
            'owner_name' => 'Owner',
        ];

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('create_clinic');
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
                         ->postJson('/api/v1/clinics', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['clinic_name' => 'Test Clinic']);

        $this->assertDatabaseHas('clinics', ['clinic_name' => 'Test Clinic']);
    }
}
