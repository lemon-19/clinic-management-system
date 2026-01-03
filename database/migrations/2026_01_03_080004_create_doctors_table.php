<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('specialty')->nullable();
            $table->string('sub_specialty')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_issued_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_image')->nullable();
            $table->string('signature')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
