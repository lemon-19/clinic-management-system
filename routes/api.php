<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    // Auth
    Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('register', [\App\Http\Controllers\Api\AuthController::class, 'register']);

    Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [\App\Http\Controllers\Api\AuthController::class, 'me'])->middleware('auth:sanctum');

    // Token management
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('tokens', [\App\Http\Controllers\Api\AuthController::class, 'tokens']);
        Route::delete('tokens/{id}', [\App\Http\Controllers\Api\AuthController::class, 'revokeToken']);
    });

    // Public resources (read)
    Route::apiResource('clinics', ClinicController::class)->only(['index','show']);
    Route::get('clinics/{clinic}/doctors', [ClinicController::class, 'doctors']);
    Route::get('clinics/{clinic}/services', [ClinicController::class, 'services']);

    Route::apiResource('services', ServiceController::class)->only(['index','show']);

    Route::apiResource('doctors', DoctorController::class)->only(['index','show']);
    Route::get('doctors/{doctor}/clinics', [DoctorController::class, 'clinics']);
    Route::get('doctors/{doctor}/schedules', [DoctorController::class, 'schedules']);

    Route::apiResource('users', UserController::class)->only(['index','show','store']);

    // Protected write endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('clinics', [ClinicController::class, 'store']);
        Route::put('clinics/{clinic}', [ClinicController::class, 'update']);
        Route::patch('clinics/{clinic}', [ClinicController::class, 'update']);
        Route::delete('clinics/{clinic}', [ClinicController::class, 'destroy']);
        Route::post('clinics/{clinic}/restore', [ClinicController::class, 'restore']);

        Route::post('services', [ServiceController::class, 'store']);
        Route::put('services/{service}', [ServiceController::class, 'update']);
        Route::patch('services/{service}', [ServiceController::class, 'update']);
        Route::delete('services/{service}', [ServiceController::class, 'destroy']);
        Route::post('services/{id}/restore', [ServiceController::class, 'restore']);

        Route::post('doctors', [DoctorController::class, 'store']);
        Route::put('doctors/{doctor}', [DoctorController::class, 'update']);
        Route::patch('doctors/{doctor}', [DoctorController::class, 'update']);
        Route::delete('doctors/{doctor}', [DoctorController::class, 'destroy']);
        Route::post('doctors/{id}/restore', [DoctorController::class, 'restore']);

        Route::post('users/{id}/restore', [UserController::class, 'restore']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::post('users/{user}/password', [UserController::class, 'changePassword']);
        // Change current user's password
        Route::post('me/password', [UserController::class, 'changeMyPassword']);

        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
        Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);
        Route::post('appointments/{id}/restore', [AppointmentController::class, 'restore']);

        // Me endpoints
        Route::get('me/appointments', [AppointmentController::class, 'index'])->name('me.appointments');
    });
});
