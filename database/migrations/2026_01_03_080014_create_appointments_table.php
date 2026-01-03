<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->nullable();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->enum('appointment_type', ['in_person','telemedicine'])->default('in_person');
            $table->date('appointment_date')->nullable();
            $table->dateTime('appointment_time')->nullable();
            $table->enum('status', ['pending','confirmed','completed','cancelled','no_show'])->default('pending');
            $table->text('reason')->nullable();
            $table->text('patient_notes')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['clinic_id', 'doctor_id', 'appointment_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
