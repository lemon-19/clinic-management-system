<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Define all permissions
        $permissions = [
            // Clinic permissions
            'view_clinics',
            'create_clinic',
            'update_clinic',
            'delete_clinic',
            'restore_clinic',
            
            // Doctor permissions
            'view_doctors',
            'create_doctor',
            'update_doctor',
            'delete_doctor',
            'restore_doctor',
            
            // Service permissions
            'view_services',
            'create_service',
            'update_service',
            'delete_service',
            'restore_service',
            
            // Appointment permissions
            'view_appointments',
            'create_appointment',
            'update_appointment',
            'cancel_appointment',
            'confirm_appointment',
            'complete_appointment',
            'reschedule_appointment',
            'restore_appointment',
            'bulk_cancel_appointments',
            'bulk_reschedule_appointments',
            
            // Medical record permissions
            'view_medical_records',
            'create_medical_record',
            'update_medical_record',
            'delete_medical_record',
            'restore_medical_record',
            'manage_medical_record_visibility',
            
            // User permissions
            'view_users',
            'create_user',
            'update_user',
            'delete_user',
            'restore_user',
            'change_user_password',
            
            // Staff management
            'manage_clinic_staff',
            'view_staff',
            'create_staff',
            'update_staff',
            'delete_staff',
            
            // Inventory permissions
            'view_inventory',
            'create_inventory',
            'update_inventory',
            'delete_inventory',
            
            // Analytics & Reports
            'view_analytics',
            'view_reports',
            'export_reports',
            
            // System admin
            'manage_roles',
            'manage_permissions',
            'view_activity_logs',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define roles and assign permissions
        
        // ADMIN ROLE - Has all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions($permissions);

        // DOCTOR ROLE
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
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

        // SECRETARY ROLE
        $secretaryRole = Role::firstOrCreate(['name' => 'secretary']);
        $secretaryPermissions = [
            'view_appointments',
            'create_appointment',
            'update_appointment',
            'cancel_appointment',
            'reschedule_appointment',
            'view_medical_records',
            'view_users',
            'view_clinics',
            'view_services',
            'manage_clinic_staff',
            'view_staff',
        ];
        $secretaryRole->syncPermissions($secretaryPermissions);

        // PATIENT ROLE
        $patientRole = Role::firstOrCreate(['name' => 'patient']);
        $patientPermissions = [
            'view_appointments',
            'create_appointment',
            'cancel_appointment',
            'reschedule_appointment',
            'view_medical_records',
            'view_clinics',
            'view_services',
            'view_doctors',
        ];
        $patientRole->syncPermissions($patientPermissions);
    }
}