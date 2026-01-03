<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->integer('rating')->default(0);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_ratings');
    }
};
