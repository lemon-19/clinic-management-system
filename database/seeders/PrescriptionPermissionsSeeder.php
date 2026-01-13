<?php
// ============================================
// database/seeders/PrescriptionPermissionsSeeder.php
// ============================================

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PrescriptionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'view_prescriptions',
            'create_prescriptions',
            'update_prescriptions',
            'delete_prescriptions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(array_merge(
                $adminRole->permissions->pluck('name')->toArray(),
                $permissions
            ));
        }

        // Assign to doctor role (create, view, update)
        $doctorRole = Role::where('name', 'doctor')->first();
        if ($doctorRole) {
            $doctorRole->syncPermissions(array_merge(
                $doctorRole->permissions->pluck('name')->toArray(),
                ['view_prescriptions', 'create_prescriptions', 'update_prescriptions']
            ));
        }
    }
}
?>