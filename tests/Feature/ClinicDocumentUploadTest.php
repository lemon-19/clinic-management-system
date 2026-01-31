<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_document(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create();
        $clinic = Clinic::factory()->create();
        ClinicStaff::create(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'role' => 'owner']);

        $this->assertTrue(\App\Models\ClinicStaff::where('clinic_id', $clinic->id)->where('user_id', $user->id)->exists());

        $response = $this->actingAs($user, 'sanctum')->post("/api/v1/clinics/{$clinic->uuid}/documents", [
            'type' => 'proof_of_address',
            'file' => UploadedFile::fake()->create('proof.pdf', 100),
        ]);

        if ($response->status() !== 201) {
            \Illuminate\Support\Facades\Log::info('clinic upload response', ['status' => $response->status(), 'content' => $response->getContent()]);
        }

        $response->assertStatus(201);

        $this->assertDatabaseHas('clinic_documents', [
            'clinic_id' => $clinic->id,
            'type' => 'proof_of_address',
            'status' => 'pending',
            'uploaded_by' => $user->id,
        ]);

        $doc = \App\Models\ClinicDocument::first();
        Storage::disk($doc->file_disk)->assertExists($doc->file_path);
    }

    public function test_upload_validates_file_type_and_size(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create();
        $clinic = Clinic::factory()->create();
        ClinicStaff::create(['clinic_id' => $clinic->id, 'user_id' => $user->id, 'role' => 'owner']);

        $response = $this->actingAs($user, 'sanctum')->post("/api/v1/clinics/{$clinic->uuid}/documents", [
            'type' => 'owner_valid_id',
            'file' => UploadedFile::fake()->create('photo.exe', 100),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }
}
