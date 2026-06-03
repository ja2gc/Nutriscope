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
        Schema::create('budget_daily_logs', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->decimal('planned', 10, 2)->default(0);
            $table->decimal('actual', 10, 2)->default(0);
            $table->decimal('variance', 10, 2)->default(0);
            $table->date('log_date')->nullable();
            $table->decimal('spent', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_daily_logs');
    }
};
