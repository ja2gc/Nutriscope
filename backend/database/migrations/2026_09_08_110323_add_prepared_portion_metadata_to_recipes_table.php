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
        Schema::table('recipes', function (Blueprint $table): void {
            $table->string('component_type')->nullable()->after('meal_types')->index();
            $table->decimal('prepared_portion_amount', 8, 2)->nullable()->after('servings');
            $table->string('prepared_portion_unit')->nullable()->after('prepared_portion_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropIndex(['component_type']);
            $table->dropColumn(['component_type', 'prepared_portion_amount', 'prepared_portion_unit']);
        });
    }
};
