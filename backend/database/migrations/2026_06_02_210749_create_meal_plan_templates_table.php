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
        Schema::create('meal_plan_templates', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('rnd_user_id')->references('id')->on('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('goal_type')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_templates');
    }
};
