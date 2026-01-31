<?php
// ============================================
// database/migrations/YYYY_MM_DD_create_notification_logs_table.php
// ============================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('notifications_custom')->nullOnDelete();
            $table->string('channel'); // email, sms, in_app
            $table->string('status'); // successful, failed, pending
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('reference_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'channel']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
