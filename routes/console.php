<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Example: Test sending a notification
Artisan::command('notification:test {userId}', function ($userId) {
    $service = app(\App\Services\NotificationService::class);
    
    $service->createInAppNotification(
        userId: (int)$userId,
        type: 'test',
        title: 'Test Notification',
        message: 'This is a test notification from the CLI',
        data: ['test' => true, 'timestamp' => now()]
    );
    
    $this->info('Test notification created for user ' . $userId);
})->purpose('Send a test notification to a user');

// Example: Test appointment reminder
Artisan::command('notification:test-appointment {appointmentId}', function ($appointmentId) {
    $appointment = \App\Models\Appointment::find($appointmentId);
    
    if (!$appointment) {
        $this->error('Appointment not found');
        return;
    }
    
    $service = app(\App\Services\NotificationService::class);
    $service->sendAppointmentReminder($appointment, 24);
    
    $this->info('Test appointment reminder sent for appointment ' . $appointmentId);
})->purpose('Send a test appointment reminder');