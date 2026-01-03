<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('unit')->nullable();
            $table->integer('critical_level')->default(0);
            $table->date('expiry_date')->nullable();
            $table->string('batch_number')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
