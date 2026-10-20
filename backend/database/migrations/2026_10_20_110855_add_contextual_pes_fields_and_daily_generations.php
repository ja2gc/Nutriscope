<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table): void {
            $table->json('builder_data')->nullable();
            $table->json('ai_provenance')->nullable();
            $table->uuid('ai_draft_id')->nullable()->unique();
        });
        Schema::create('patient_pes_daily_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('generation_date');
            $table->unsignedTinyInteger('generations')->default(0);
            $table->unique(['patient_id', 'generation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_pes_daily_generations');
        Schema::table('diagnoses', function (Blueprint $table): void {
            $table->dropUnique(['ai_draft_id']);
            $table->dropColumn(['builder_data', 'ai_provenance', 'ai_draft_id']);
        });
    }
};
