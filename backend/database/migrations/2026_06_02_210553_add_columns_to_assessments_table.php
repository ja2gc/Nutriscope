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
        Schema::table('assessments', function (Blueprint $table) {
            $table->decimal('usual_weight', 6, 2)->nullable();
            $table->enum('nutritional_status', ['Normal', 'Moderate Malnutrition', 'Severe Malnutrition'])->nullable();
            $table->decimal('weight_loss_percentage', 5, 2)->nullable();
            $table->string('weight_loss_period')->nullable();
            $table->enum('functional_assessment', ['Bed ridden', 'Needs assistance', 'Ambulatory'])->nullable();
            $table->enum('energy_intake_status', ['No change', 'Mostly liquids', 'Sub-optimal', 'Starvation', 'Poor intake prior to admission'])->nullable();
            $table->decimal('ibw_percentage', 5, 2)->nullable();
            $table->text('present_diet')->nullable();
            $table->text('physical_assessment')->nullable();
            $table->text('chewing_swallowing_difficulties')->nullable();
            $table->text('constipation')->nullable();
            $table->text('diarrhea_notes')->nullable();
            $table->text('food_intolerance')->nullable();
            $table->text('nutrient_drug_interaction')->nullable();
            $table->enum('dietary_intake_method', ['24_hour_recall', 'food_frequency', '3_day_record', 'other'])->nullable();
            $table->string('dietary_record_file')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn([
                'usual_weight',
                'nutritional_status',
                'weight_loss_percentage',
                'weight_loss_period',
                'functional_assessment',
                'energy_intake_status',
                'ibw_percentage',
                'present_diet',
                'physical_assessment',
                'chewing_swallowing_difficulties',
                'constipation',
                'diarrhea_notes',
                'food_intolerance',
                'nutrient_drug_interaction',
                'dietary_intake_method',
                'dietary_record_file',
            ]);
        });
    }
};
