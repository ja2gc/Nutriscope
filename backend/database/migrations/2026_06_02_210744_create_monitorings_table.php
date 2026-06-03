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
        Schema::create('monitorings', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('ncp_record_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->json('lab_values')->nullable();
            $table->text('intake_notes')->nullable();
            $table->text('symptoms')->nullable();
            $table->json('goal_achievement')->nullable();
            $table->text('clinical_summary')->nullable();
            $table->string('ai_decision')->nullable();
            $table->date('next_monitoring_date')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitorings');
    }
};
