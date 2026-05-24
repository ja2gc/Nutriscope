<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biochemical_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->decimal('albumin', 6, 2)->nullable();
            $table->decimal('hematocrit', 6, 2)->nullable();
            $table->decimal('bun', 6, 2)->nullable();
            $table->decimal('hemoglobin', 6, 2)->nullable();
            $table->decimal('calcium', 6, 2)->nullable();
            $table->decimal('ldl', 6, 2)->nullable();
            $table->decimal('cholesterol', 6, 2)->nullable();
            $table->decimal('phosphate', 6, 2)->nullable();
            $table->decimal('creatinine', 6, 2)->nullable();
            $table->decimal('potassium', 6, 2)->nullable();
            $table->decimal('glucose', 6, 2)->nullable();
            $table->decimal('sodium', 6, 2)->nullable();
            $table->decimal('hba1c', 6, 2)->nullable();
            $table->decimal('triglycerides', 6, 2)->nullable();
            $table->decimal('hdl', 6, 2)->nullable();
            $table->decimal('urr', 6, 2)->nullable();
            $table->string('bp')->nullable();
            $table->string('abg')->nullable();
            $table->json('others')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biochemical_data');
    }
};
