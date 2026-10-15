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
        Schema::table('food_items', function (Blueprint $table): void {
            $table->decimal('serving_size', 12, 6)->default(100)->change();
        });
        Schema::table('recipe_ingredients', function (Blueprint $table): void {
            $table->decimal('quantity', 16, 6)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Clinical quantity precision requires a forward fix; reducing it could discard measured amounts.');
    }
};
