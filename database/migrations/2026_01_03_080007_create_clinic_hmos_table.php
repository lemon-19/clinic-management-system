<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_hmos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('hmo_name');
            $table->timestamps();

            $table->index(['clinic_id', 'hmo_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_hmos');
    }
};
