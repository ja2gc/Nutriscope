<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_service_recipes', function (Blueprint $table): void {
            $table->string('portion_label')->nullable()->after('servings');
        });
    }

    public function down(): void
    {
        Schema::table('food_service_recipes', function (Blueprint $table): void {
            $table->dropColumn('portion_label');
        });
    }
};
