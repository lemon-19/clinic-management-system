<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->nullable();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('visit_date')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_visible_to_patient')->default(true);
            $table->json('allergies')->nullable();
            $table->json('medications')->nullable();
            $table->json('medical_history')->nullable();
            $table->json('family_history')->nullable();
            $table->json('social_history')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
