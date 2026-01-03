<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->date('issued_date')->nullable();
            $table->boolean('is_visible_to_patient')->default(true);
            $table->timestamps();

            $table->index(['medical_record_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_documents');
    }
};
