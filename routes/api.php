<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\PasswordController; // <-- Correct namespace
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    // ------------------ AUTH (NO AUTH REQUIRED) ------------------
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Public route to check availability
    Route::get('doctor-schedules/available-slots', [DoctorScheduleController::class, 'getAvailableSlots'])
        ->name('doctor-schedules.available-slots.public');

    // ------------------ AUTHENTICATED ROUTES ------------------
    Route::middleware('auth:sanctum')->group(function () {
        // User info & logout
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::middleware('permission:view_users')->get('users', [UserController::class, 'index']);
        Route::middleware('permission:create_users')->post('users', [UserController::class, 'store']);

        // User permissions
        Route::get('me/permissions', [RolePermissionController::class, 'getUserPermissions']);

        // ------------------ TOKEN MANAGEMENT ------------------
        Route::get('tokens', [AuthController::class, 'tokens']);
        Route::delete('tokens/{id}', [AuthController::class, 'revokeToken']);

        // ------------------ PASSWORD CHANGE ------------------
        Route::post('me/password', [PasswordController::class, 'update']); // <-- Updated namespace

        // ------------------ CLINICS ------------------
        Route::middleware('permission:view_clinics')->get('clinics', [ClinicController::class, 'index']);
        Route::middleware('permission:view_clinics')->get('clinics/{clinic}', [ClinicController::class, 'show']);
        Route::middleware('permission:create_clinic')->post('clinics', [ClinicController::class, 'store']);
        Route::middleware('permission:update_clinic')->put('clinics/{clinic}', [ClinicController::class, 'update']);
        Route::middleware('permission:delete_clinic')->delete('clinics/{clinic}', [ClinicController::class, 'destroy']);
        Route::middleware('permission:restore_clinic')->post('clinics/{clinic}/restore', [ClinicController::class, 'restore']);

        // ------------------ DOCTORS ------------------
        Route::middleware('permission:view_doctors')->get('doctors', [DoctorController::class, 'index']);
        Route::middleware('permission:view_doctors')->get('doctors/{doctor}', [DoctorController::class, 'show']);
        Route::middleware('permission:create_doctor')->post('doctors', [DoctorController::class, 'store']);
        Route::middleware('permission:update_doctor')->put('doctors/{doctor}', [DoctorController::class, 'update']);
        Route::middleware('permission:delete_doctor')->delete('doctors/{doctor}', [DoctorController::class, 'destroy']);

        // ------------------ APPOINTMENTS ------------------
        Route::middleware('permission:cancel_appointment')->post('appointments/bulk-cancel', [AppointmentController::class, 'bulkCancel']);
        Route::middleware('permission:update_appointment')->post('appointments/bulk-reschedule-conflicts', [AppointmentController::class, 'bulkRescheduleConflicts']);

        Route::middleware('permission:view_appointments')->get('appointments', [AppointmentController::class, 'index']);
        Route::middleware('permission:create_appointment')->post('appointments', [AppointmentController::class, 'store']);
        Route::middleware('permission:view_appointments')->get('appointments/{appointmentId}', [AppointmentController::class, 'show']);
        Route::middleware('permission:update_appointment')->patch('appointments/{appointmentId}', [AppointmentController::class, 'update']);
        Route::middleware('permission:cancel_appointment')->post('appointments/{appointmentId}/cancel', [AppointmentController::class, 'cancel']);
        Route::middleware('permission:confirm_appointment')->post('appointments/{appointmentId}/confirm', [AppointmentController::class, 'confirm']);
        Route::middleware('permission:complete_appointment')->post('appointments/{appointmentId}/complete', [AppointmentController::class, 'complete']);
        Route::middleware('permission:update_appointment')->post('appointments/{appointmentId}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::middleware('permission:delete_appointment')->delete('appointments/{appointmentId}', [AppointmentController::class, 'destroy']);
        Route::middleware('permission:delete_appointment')->post('appointments/{appointmentId}/restore', [AppointmentController::class, 'restore']);

        // ------------------ MEDICAL RECORDS ------------------
        Route::middleware('permission:view_medical_records')->get('patients/{patientId}/medical-records', [MedicalRecordController::class, 'indexByPatient']);
        Route::middleware('permission:view_medical_records')->get('clinics/{clinicId}/medical-records', [MedicalRecordController::class, 'indexByClinic']);
        Route::middleware('permission:create_medical_record')->post('medical-records', [MedicalRecordController::class, 'store']);
        Route::middleware('permission:view_medical_records')->get('medical-records/{recordId}', [MedicalRecordController::class, 'show']);
        Route::middleware('permission:update_medical_record')->put('medical-records/{recordId}', [MedicalRecordController::class, 'update']);
        Route::middleware('permission:delete_medical_record')->delete('medical-records/{recordId}', [MedicalRecordController::class, 'destroy']);
        Route::middleware('role:admin')->post('medical-records/{recordId}/restore', [MedicalRecordController::class, 'restore']);
        Route::post('medical-records/{recordId}/restore', [MedicalRecordController::class, 'restore'])->middleware('auth:sanctum');
        Route::patch('medical-records/{recordId}/visibility', [MedicalRecordController::class, 'toggleVisibility'])->middleware('auth:sanctum');

        // ------------------ DOCTOR SCHEDULE ------------------
        Route::apiResource('doctor-schedules', DoctorScheduleController::class);
        Route::post('doctor-schedules/bulk-update', [DoctorScheduleController::class, 'bulkUpdate']);
        Route::post('doctor-schedules/check-conflicts', [DoctorScheduleController::class, 'checkConflicts']);
        Route::get('doctor-schedules/available-slots', [DoctorScheduleController::class, 'getAvailableSlots']);
        Route::patch('doctor-schedules/{scheduleId}/toggle-availability', [DoctorScheduleController::class, 'toggleAvailability']);
        Route::get('doctors/{doctorId}/schedules', [DoctorScheduleController::class, 'getDoctorSchedules']);

        // ------------------ ROLE & PERMISSION MANAGEMENT ------------------
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
