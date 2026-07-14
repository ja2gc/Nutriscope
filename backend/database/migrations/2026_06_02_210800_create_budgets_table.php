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
        Schema::create('budgets', function (Blueprint $table) {

            $table->id();
            $table->foreignId('rnd_user_id')->references('id')->on('users');
            $table->decimal('planned_amount', 10, 2);
            $table->decimal('actual_amount', 10, 2)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('cost_per_person', 8, 2)->default(150);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
