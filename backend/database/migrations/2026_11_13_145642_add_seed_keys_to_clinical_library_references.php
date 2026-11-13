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
        foreach (['recipes', 'meal_plan_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('seed_key', 100)->nullable();
                $table->unique(['rnd_user_id', 'seed_key']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['recipes', 'meal_plan_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['rnd_user_id', 'seed_key']);
                $table->dropColumn('seed_key');
            });
        }
    }
};
