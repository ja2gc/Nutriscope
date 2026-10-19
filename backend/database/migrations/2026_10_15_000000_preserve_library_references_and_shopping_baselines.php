<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Archive columns must exist before historical migrations query the current models on a fresh database. */
    public function up(): void
    {
        foreach (['food_items', 'recipes'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->json('generation_baseline')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['food_items', 'recipes'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->dropColumn('generation_baseline');
        });
    }
};
