<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hmos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('hmo_name');
            $table->string('member_id')->nullable();
            $table->date('validity_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'hmo_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hmos');
    }
};
