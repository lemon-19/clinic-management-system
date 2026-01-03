<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('test_name');
            $table->date('test_date')->nullable();
            $table->text('result')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_visible_to_patient')->default(true);
            $table->timestamps();

            $table->index(['medical_record_id', 'test_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
