<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_conferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('platform')->nullable();
            $table->string('meeting_id')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('password')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_conferences');
    }
};
