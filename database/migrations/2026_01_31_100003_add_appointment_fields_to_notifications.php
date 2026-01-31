<?php
// ============================================
// database/migrations/2026_01_31_100003_add_appointment_fields_to_notifications.php
// ============================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_custom', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_id')->nullable()->after('data');
            $table->integer('hours_before')->nullable()->after('appointment_id');
            // Add composite unique index to prevent duplicate reminders (user, type, appointment, hours)
            $table->unique(['user_id', 'type', 'appointment_id', 'hours_before'], 'notifications_unique_reminder');
        });

        // Backfill appointment_id and hours_before from JSON data for existing reminders
        $items = DB::table('notifications_custom')
            ->where('type', 'appointment_reminder')
            ->get();

        foreach ($items as $item) {
            $data = json_decode($item->data, true) ?? [];
            DB::table('notifications_custom')->where('id', $item->id)->update([
                'appointment_id' => $data['appointment_id'] ?? null,
                'hours_before' => $data['hours_before'] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('notifications_custom', function (Blueprint $table) {
            $table->dropUnique('notifications_unique_reminder');
            $table->dropColumn(['appointment_id', 'hours_before']);
        });
    }
};