<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('image_path');
            $table->string('type')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['clinic_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_images');
    }
};
