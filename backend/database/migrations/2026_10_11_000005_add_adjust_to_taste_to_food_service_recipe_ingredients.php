<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_service_recipe_ingredients', function (Blueprint $table): void {
            $table->boolean('adjust_to_taste')->default(false)->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('food_service_recipe_ingredients', function (Blueprint $table): void {
            $table->dropColumn('adjust_to_taste');
        });
    }
};
