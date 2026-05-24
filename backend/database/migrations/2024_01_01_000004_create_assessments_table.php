<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncp_record_id')->constrained()->cascadeOnDelete();
            // Dietary (D)
            $table->text('dietary_intake')->nullable();
            $table->text('appetite_changes')->nullable();
            $table->text('dietary_restrictions')->nullable();
            $table->text('supplements')->nullable();
            $table->text('knowledge_notes')->nullable();
            // Anthropometric (A)
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('height', 6, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->string('body_composition')->nullable();
            // Client History (C)
            $table->text('medical_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('lifestyle')->nullable();
            $table->json('allergies')->nullable();       // hard filter
            $table->json('food_dislikes')->nullable();   // soft filter
            $table->json('medications')->nullable();     // food-drug interaction check
            // RND Summary
            $table->text('rnd_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
