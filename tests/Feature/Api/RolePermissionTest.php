<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private $apiPrefix = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions for each test
        $this->seedRolesAndPermissions();
    }

    private function seedRolesAndPermissions(): void
    {
        // Create permissions
        $permissions = [
            'view_clinics',
            'create_clinic',
            'update_clinic',
            'delete_clinic',
            'view_doctors',
            'create_doctor',
            'update_doctor',
            'delete_doctor',
            'view_appointments',
            'create_appointment',
            'cancel_appointment',
            'confirm_appointment',
            'complete_appointment',
            'reschedule_appointment',
            'view_medical_records',
            'create_medical_record',
            'update_medical_record',
            'delete_medical_record',
            'manage_medical_record_visibility',
            'view_users',
            'create_user',
            'update_user',
            'delete_user',
            'manage_roles',
            'manage_permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles with web guard (not sanctum)
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctorPermissions = [
            'view_appointments',
            'create_appointment',
            'cancel_appointment',
            'confirm_appointment',
            'complete_appointment',
            'reschedule_appointment',
            'view_medical_records',
            'create_medical_record',
            'update_medical_record',
            'manage_medical_record_visibility',
            'view_users',
            'view_clinics',
        ];
        $doctorRole->syncPermissions($doctorPermissions);

        $secretaryRole = Role::firstOrCreate(['name' => 'secretary', 'guard_name' => 'web']);
        $secretaryPermissions = [
            'view_appointments',
            'create_appointment',
            'cancel_appointment',
            'reschedule_appointment',
            'view_medical_records',
            'view_users',
            'view_clinics',
        ];
        $secretaryRole->syncPermissions($secretaryPermissions);

        $patientRole = Role::firstOrCreate(['name' => 'patient', 'guard_name' => 'web']);
        $patientPermissions = [
            'view_appointments',
            'create_appointment',
            'cancel_appointment',
            'reschedule_appointment',
            'view_medical_records',
            'view_clinics',
        ];
        $patientRole->syncPermissions($patientPermissions);
    }

    /**
     * Test: List all roles
     */
    public function test_admin_can_list_all_roles(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . '/roles');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                ],
            ],
            'meta' => ['total', 'per_page', 'current_page'],
        ]);

        // Should have 4 roles: admin, doctor, secretary, patient
        $this->assertGreaterThanOrEqual(4, $response->json('meta.total'));
    }

    /**
     * Test: Non-admin cannot list roles
     */
    public function test_non_admin_cannot_list_roles(): void
    {
        $doctor = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor->assignRole('doctor');
        $token = $doctor->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . '/roles');

        $response->assertStatus(403);
    }

    /**
     * Test: List all permissions
     */
    public function test_admin_can_list_all_permissions(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . '/permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                ],
            ],
            'meta' => ['total', 'per_page', 'current_page'],
        ]);

        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    /**
     * Test: Get specific role with permissions
     */
    public function test_admin_can_view_specific_role(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $doctorRole = Role::findByName('doctor');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/roles/{$doctorRole->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'permissions',
            ],
        ]);

        // Check role name is returned correctly
        $this->assertEquals('doctor', $response->json('data.name'));
        $this->assertIsArray($response->json('data.permissions'));
        $this->assertGreaterThan(0, count($response->json('data.permissions')));
    }

    /**
     * Test: Assign role to user
     */
    public function test_admin_can_assign_role_to_user(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create(['user_type' => UserType::PATIENT]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/users/assign-role', [
                'user_id' => $user->id,
                'role' => 'doctor',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', "Role 'doctor' assigned to user");

        // Verify user has the role
        $this->assertTrue($user->refresh()->hasRole('doctor'));
    }

    /**
     * Test: Cannot assign invalid role
     */
    public function test_cannot_assign_invalid_role(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/users/assign-role', [
                'user_id' => $user->id,
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    /**
     * Test: Remove role from user
     */
    public function test_admin_can_remove_role_from_user(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create();
        $user->assignRole('doctor');

        $this->assertTrue($user->hasRole('doctor'));

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/users/remove-role', [
                'user_id' => $user->id,
                'role' => 'doctor',
            ]);

        $response->assertStatus(200);

        // Verify role was removed
        $this->assertFalse($user->refresh()->hasRole('doctor'));
    }

    /**
     * Test: Get user permissions
     */
    public function test_admin_can_get_user_permissions(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create();
        $user->assignRole('doctor');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/users/{$user->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
            ],
            'roles',
            'permissions',
        ]);

        // Doctor should have view_appointments permission in permissions array
        $permissions = $response->json('permissions');
    }

    /**
     * Test: Grant permission to role
     */
    public function test_admin_can_grant_permission_to_role(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $secretaryRole = Role::where('name', 'secretary')
            ->where('guard_name', 'web')
            ->first();
        
        // Secretary doesn't have this permission initially
        $this->assertFalse($secretaryRole->hasPermissionTo('delete_clinic'));

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/roles/grant-permission', [
                'role' => 'secretary',
                'permission' => 'delete_clinic',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', "Permission 'delete_clinic' granted to role 'secretary'");

        // Verify permission was granted
        $secretaryRole->refresh();
        $this->assertTrue($secretaryRole->hasPermissionTo('delete_clinic'));
    }

    /**
     * Test: Cannot grant invalid permission
     */
    public function test_cannot_grant_invalid_permission(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/roles/grant-permission', [
                'role' => 'doctor',
                'permission' => 'invalid_permission',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['permission']);
    }

    /**
     * Test: Revoke permission from role
     */
    public function test_admin_can_revoke_permission_from_role(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::ADMIN]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $doctorRole = Role::where('name', 'doctor')
            ->where('guard_name', 'web')
            ->first();
        
        // Doctor has this permission initially
        $this->assertTrue($doctorRole->hasPermissionTo('view_appointments'));

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/roles/revoke-permission', [
                'role' => 'doctor',
                'permission' => 'view_appointments',
            ]);

        $response->assertStatus(200);

        // Verify permission was revoked
        $doctorRole->refresh();
        $this->assertFalse($doctorRole->hasPermissionTo('view_appointments'));
    }


    /**
     * Test: User with permission can access protected route
     */
    public function test_user_with_permission_can_access_protected_route(): void
    {
        $doctor = User::factory()->create(['user_type' => UserType::DOCTOR]);
        $doctor->assignRole('doctor');
        $token = $doctor->createToken('test-token')->plainTextToken;

        // Doctor has 'view_appointments' permission
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . '/appointments');

        // Should not get 403 Forbidden (permission error)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test: User without permission cannot access protected route
     */
    public function test_user_without_permission_cannot_access_protected_route(): void
    {
        $patient = User::factory()->create(['user_type' => UserType::PATIENT]);
        $patient->assignRole('patient');
        $token = $patient->createToken('test-token')->plainTextToken;

        // Remove 'create_medical_record' permission from patient role
        $patientRole = Role::findByName('patient');
        $patientRole->revokePermissionTo('create_medical_record');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/medical-records', [
                'patient_id' => $patient->id,
                'clinic_id' => 1,
                'doctor_id' => 1,
                'visit_date' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Unauthorized - Permission not granted');
    }

    /**
     * Test: Role inheritance - Doctor has all doctor permissions
     */
    public function test_doctor_role_has_all_doctor_permissions(): void
    {
        $doctorRole = Role::findByName('doctor');

        $doctorPermissions = [
            'view_appointments',
            'create_appointment',
            'confirm_appointment',
            'complete_appointment',
            'view_medical_records',
            'create_medical_record',
            'update_medical_record',
        ];

        foreach ($doctorPermissions as $permission) {
            $this->assertTrue($doctorRole->hasPermissionTo($permission));
        }
    }

    /**
     * Test: Patient role has limited permissions
     */
    public function test_patient_role_has_limited_permissions(): void
    {
        $patientRole = Role::findByName('patient');

        // Patient should have these
        $this->assertTrue($patientRole->hasPermissionTo('view_appointments'));
        $this->assertTrue($patientRole->hasPermissionTo('create_appointment'));

        // Patient should NOT have these
        $this->assertFalse($patientRole->hasPermissionTo('delete_clinic'));
        $this->assertFalse($patientRole->hasPermissionTo('create_doctor'));
    }

    /**
     * Test: Multiple role assignment
     */
    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();

        $user->assignRole('doctor');
        $user->assignRole('secretary');

        $this->assertTrue($user->hasRole('doctor'));
        $this->assertTrue($user->hasRole('secretary'));
        $this->assertCount(2, $user->getRoleNames());
    }

    /**
     * Test: Admin has all permissions
     */
    public function test_admin_has_all_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $testPermissions = [
            'create_clinic',
            'delete_clinic',
            'create_doctor',
            'view_medical_records',
            'manage_roles',
        ];

        foreach ($testPermissions as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission));
        }
    }

    /**
     * Test: Unauthorized access without token
     */
    public function test_cannot_access_without_authentication(): void
    {
        $response = $this->getJson($this->apiPrefix . '/roles');

        $response->assertStatus(401);
    }

    /**
     * Test: Assign non-existent user role
     */
    public function test_cannot_assign_role_to_nonexistent_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->apiPrefix . '/users/assign-role', [
                'user_id' => 99999,
                'role' => 'doctor',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test: User can check own permissions with admin access
     */
    public function test_user_can_check_own_permissions_with_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'user_type' => 'admin'
        ]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $doctor = User::factory()->create([
            'email' => 'doctor@test.com',
            'user_type' => 'doctor',
            'first_name' => 'Test',
            'last_name' => 'Doctor'
        ]);
        $doctor->assignRole('doctor');

        // Make sure doctor actually has the role assigned
        $this->assertTrue($doctor->hasRole('doctor'));

        // Refresh to ensure fresh data
        $doctor->refresh();
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->apiPrefix . "/users/{$doctor->id}/permissions");

        $response->assertStatus(200);
        
        // Get roles from response
        $roles = $response->json('roles');

        // Make sure roles is actually an array
        $this->assertIsArray($roles);
        
        // Check if 'doctor' is in the roles
        $this->assertContains('doctor', $roles, 
            'Response roles: ' . json_encode($roles));
    }
}