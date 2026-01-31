<?php
// ============================================
// database/migrations/YYYY_MM_DD_create_notification_preferences_table.php
// ============================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            
            // Notification types
            $table->boolean('appointment_confirmation')->default(true);
            $table->boolean('appointment_reminder_24h')->default(true);
            $table->boolean('appointment_reminder_1h')->default(true);
            $table->boolean('appointment_completed')->default(true);
            $table->boolean('medical_record_shared')->default(true);
            $table->boolean('prescription_added')->default(true);
            $table->boolean('test_results_ready')->default(true);
            $table->boolean('clinic_announcements')->default(true);
            $table->boolean('system_notifications')->default(true);
            
            // Channels
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            
            // Reminder times
            $table->time('reminder_24h_time')->default('09:00');
            $table->time('reminder_1h_time')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};