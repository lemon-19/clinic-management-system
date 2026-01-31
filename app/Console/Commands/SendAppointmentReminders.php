<?php
// ============================================
// app/Console/Commands/SendAppointmentReminders.php
// ============================================

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';
    protected $description = 'Send appointment reminders (24h and 1h before)';

    public function __construct(private NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // 24-hour reminders
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $appointments24h = Appointment::whereDate('appointment_date', $tomorrow)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        foreach ($appointments24h as $appointment) {
            $this->notificationService->sendAppointmentReminder($appointment, 24);
        }

        $this->info("Sent {$appointments24h->count()} 24-hour reminders");

        // 1-hour reminders
        $now = Carbon::now();
        $oneHourFromNow = $now->copy()->addHour();

        // Get today's appointments in candidate set, filter in PHP to ensure between now and +1 hour
        $appointmentsCandidates = Appointment::whereDate('appointment_date', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $sent1h = 0;
        foreach ($appointmentsCandidates as $appointment) {
            $appointmentDateTime = Carbon::parse($appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time->format('H:i:s'));
            if ($appointmentDateTime->greaterThan($now) && $appointmentDateTime->lessThanOrEqualTo($oneHourFromNow)) {
                $this->notificationService->sendAppointmentReminder($appointment, 1);
                $sent1h++;
            }
        }

        $this->info("Sent {$sent1h} 1-hour reminders");

        return 0;
    }
}