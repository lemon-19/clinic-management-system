<?php

namespace Tests\Feature\Api;

use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_clinics(): void
    {
        Clinic::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/clinics');

        $response->assertStatus(200)->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_clinic(): void
    {
        $payload = [
            'clinic_name' => 'Test Clinic',
            'owner_name' => 'Owner',
        ];

        $user = \App\Models\User::factory()->create();

        $token = $user->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/clinics', $payload);

        $response->assertStatus(201)->assertJsonFragment(['clinic_name' => 'Test Clinic']);
        $this->assertDatabaseHas('clinics', ['clinic_name' => 'Test Clinic']);
    }
}
