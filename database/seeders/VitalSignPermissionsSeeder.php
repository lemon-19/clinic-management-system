<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VitalSignPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'view_vital_signs',
            'create_vital_signs',
            'update_vital_signs',
            'delete_vital_signs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['guard_name' => 'api']
            );
        }

        // Assign to admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(array_merge(
                $adminRole->permissions->pluck('name')->toArray(),
                $permissions
            ));
        }

        // Assign to doctor role (view, create, update)
        $doctorRole = Role::where('name', 'doctor')->first();
        if ($doctorRole) {
            $doctorRole->syncPermissions(array_merge(
                $doctorRole->permissions->pluck('name')->toArray(),
                ['view_vital_signs', 'create_vital_signs', 'update_vital_signs']
            ));
        }
    }
}