<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\RolePermissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    // Auth routes (no permission needed)
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        // Auth endpoints
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('me/permissions', [RolePermissionController::class, 'getUserPermissions']);

        // ===== CLINIC ROUTES WITH PERMISSION CHECKS =====
        Route::middleware('permission:view_clinics')->get('clinics', [ClinicController::class, 'index']);
        Route::middleware('permission:view_clinics')->get('clinics/{clinic}', [ClinicController::class, 'show']);
        Route::middleware('permission:create_clinic')->post('clinics', [ClinicController::class, 'store']);
        Route::middleware('permission:update_clinic')->put('clinics/{clinic}', [ClinicController::class, 'update']);
        Route::middleware('permission:delete_clinic')->delete('clinics/{clinic}', [ClinicController::class, 'destroy']);
        Route::middleware('permission:restore_clinic')->post('clinics/{clinic}/restore', [ClinicController::class, 'restore']);

        // ===== DOCTOR ROUTES WITH PERMISSION CHECKS =====
        Route::middleware('permission:view_doctors')->get('doctors', [DoctorController::class, 'index']);
        Route::middleware('permission:view_doctors')->get('doctors/{doctor}', [DoctorController::class, 'show']);
        Route::middleware('permission:create_doctor')->post('doctors', [DoctorController::class, 'store']);
        Route::middleware('permission:update_doctor')->put('doctors/{doctor}', [DoctorController::class, 'update']);
        Route::middleware('permission:delete_doctor')->delete('doctors/{doctor}', [DoctorController::class, 'destroy']);

        // ===== APPOINTMENT ROUTES WITH PERMISSION CHECKS =====
        Route::middleware('permission:view_appointments')->get('appointments', [AppointmentController::class, 'index']);
        Route::middleware('permission:create_appointment')->post('appointments', [AppointmentController::class, 'store']);
        Route::middleware('permission:view_appointments')->get('appointments/{appointmentId}', [AppointmentController::class, 'show']);
        Route::middleware('permission:cancel_appointment')->post('appointments/{appointmentId}/cancel', [AppointmentController::class, 'cancel']);
        Route::middleware('permission:confirm_appointment')->post('appointments/{appointmentId}/confirm', [AppointmentController::class, 'confirm']);
        Route::middleware('permission:complete_appointment')->post('appointments/{appointmentId}/complete', [AppointmentController::class, 'complete']);

        // ===== MEDICAL RECORDS ROUTES WITH PERMISSION CHECKS =====
        Route::middleware('permission:view_medical_records')->get('patients/{patientId}/medical-records', [MedicalRecordController::class, 'indexByPatient']);
        Route::middleware('permission:create_medical_record')->post('medical-records', [MedicalRecordController::class, 'store']);
        Route::middleware('permission:view_medical_records')->get('medical-records/{recordId}', [MedicalRecordController::class, 'show']);
        Route::middleware('permission:update_medical_record')->put('medical-records/{recordId}', [MedicalRecordController::class, 'update']);
        Route::middleware('permission:delete_medical_record')->delete('medical-records/{recordId}', [MedicalRecordController::class, 'destroy']);

        // ===== ROLE & PERMISSION MANAGEMENT (ADMIN ONLY) =====
        Route::middleware('role:admin')->group(function () {
            Route::get('roles', [RolePermissionController::class, 'listRoles']);
            Route::get('permissions', [RolePermissionController::class, 'listPermissions']);
            Route::get('roles/{id}', [RolePermissionController::class, 'showRole']);
            
            Route::post('users/assign-role', [RolePermissionController::class, 'assignRoleToUser']);
            Route::post('users/remove-role', [RolePermissionController::class, 'removeRoleFromUser']);
            Route::get('users/{user}/permissions', [RolePermissionController::class, 'getUserPermissions']);
            
            Route::post('roles/grant-permission', [RolePermissionController::class, 'grantPermissionToRole']);
            Route::post('roles/revoke-permission', [RolePermissionController::class, 'revokePermissionFromRole']);
        });


    });
});