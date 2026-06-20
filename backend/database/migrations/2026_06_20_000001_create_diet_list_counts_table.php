<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_list_counts', function (Blueprint $table) {
            $table->id();
            $table->date('service_date');
            $table->foreignId('menu_cycle_id')->nullable()->constrained('menu_cycles')->nullOnDelete();
            $table->string('ward');
            $table->foreignId('fss_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('population');

            // Accomplishment-report task marks (row 4 headcount above; row 3 = collected_diet_list)
            $table->boolean('helped_food_prep')->default(false);
            $table->boolean('stored_supplies')->default(false);
            $table->boolean('collected_diet_list')->default(false);
            $table->boolean('apportioned_food')->default(false);
            $table->boolean('cleaned_utensils')->default(false);
            $table->boolean('assistant_cook')->default(false);
            $table->boolean('maintained_cleanliness')->default(false);
            $table->boolean('off_duty')->default(false);

            $table->timestamps();

            $table->index(['service_date', 'menu_cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_list_counts');
    }
};
