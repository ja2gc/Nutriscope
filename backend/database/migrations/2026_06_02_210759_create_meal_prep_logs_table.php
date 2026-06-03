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
        Schema::create('meal_prep_logs', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('fss_user_id')->references('id')->on('users');
            $table->foreignId('menu_cycle_day_id')->constrained()->references('id')->on('menu_cycle_days');
            $table->decimal('prepared_quantity', 8, 2)->nullable();
            $table->enum('status', ['done', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_prep_logs');
    }
};
