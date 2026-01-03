<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->nullable();
            $table->string('clinic_name');
            $table->string('clinic_type')->nullable();
            $table->string('owner_name')->nullable();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['pending','approved','rejected','active','archived'])->default('pending');
            $table->text('status_remarks')->nullable();
            $table->text('description')->nullable();
            $table->string('proof_of_address')->nullable();
            $table->string('business_registration')->nullable();
            $table->string('dti_permit')->nullable();
            $table->string('owner_valid_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
