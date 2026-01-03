<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->date('published_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
