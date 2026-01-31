<?php

namespace Tests\Feature;

use App\Jobs\SendEmailNotificationJob;
use App\Models\Clinic;
use App\Models\ClinicDocument;
use App\Models\ClinicStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClinicDocumentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_document_and_notify_owner(): void
    {
        Bus::fake();
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->create(['email' => 'owner@test.com']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        // Ensure the admin role exists in the test DB
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $clinic = Clinic::factory()->create();
        ClinicStaff::create(['clinic_id' => $clinic->id, 'user_id' => $owner->id, 'role' => 'owner']);

        // upload a doc
        $this->actingAs($owner, 'sanctum')->post("/api/v1/clinics/{$clinic->uuid}/documents", [
            'type' => 'proof_of_address',
            'file' => UploadedFile::fake()->create('proof.pdf', 100),
        ])->assertStatus(201);

        $doc = ClinicDocument::first();

        // Admin approves
        $this->actingAs($admin, 'sanctum')->post("/api/v1/admin/clinic-documents/{$doc->id}/review", ['status' => 'approved'])
            ->assertStatus(200);

        $this->assertDatabaseHas('clinic_documents', ['id' => $doc->id, 'status' => 'approved']);

        // In-app notification created
        $this->assertDatabaseHas('notifications_custom', ['type' => 'clinic_document_status']);

        Bus::assertDispatched(SendEmailNotificationJob::class, function ($job) use ($owner) {
            return $job->email === $owner->email;
        });
    }

    public function test_admin_can_reject_document_with_reason(): void
    {
        Bus::fake();
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->create(['email' => 'owner2@test.com']);
        $admin = User::factory()->create(['email' => 'admin2@test.com']);
        // Ensure the admin role exists in the test DB
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $clinic = Clinic::factory()->create();
        ClinicStaff::create(['clinic_id' => $clinic->id, 'user_id' => $owner->id, 'role' => 'owner']);

        $this->actingAs($owner, 'sanctum')->post("/api/v1/clinics/{$clinic->uuid}/documents", [
            'type' => 'owner_valid_id',
            'file' => UploadedFile::fake()->create('id.png', 100),
        ])->assertStatus(201);

        $doc = ClinicDocument::first();

        $this->actingAs($admin, 'sanctum')->post("/api/v1/admin/clinic-documents/{$doc->id}/review", ['status' => 'rejected', 'rejection_reason' => 'Blurry document'])
            ->assertStatus(200);

        $this->assertDatabaseHas('clinic_documents', ['id' => $doc->id, 'status' => 'rejected', 'rejection_reason' => 'Blurry document']);

        $this->assertDatabaseHas('notifications_custom', ['type' => 'clinic_document_status']);
        Bus::assertDispatched(SendEmailNotificationJob::class);
    }
}
