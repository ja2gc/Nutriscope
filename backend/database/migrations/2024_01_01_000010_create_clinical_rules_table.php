<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_rules', function (Blueprint $table) {
            $table->id();
            $table->string('condition');           // e.g. 'CKD', 'DM', 'hypertension'
            $table->string('stage')->nullable();   // e.g. 'stage3', 'stage4', 'all'
            $table->string('nutrient_or_food_tag'); // e.g. 'potassium', 'sodium', 'phosphate', 'gluten'
            $table->enum('rule_type', ['avoid', 'limit', 'recommend']);
            $table->decimal('threshold', 10, 2)->nullable(); // for 'limit' rules (e.g. max 2000mg sodium)
            $table->string('unit')->nullable();    // e.g. 'mg', 'g', 'kcal'
            $table->text('reason');
            $table->timestamps();

            $table->index(['condition', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_rules');
    }
};
