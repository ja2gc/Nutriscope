<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table): void {
            $table->json('nutrient_operators')->nullable();
            $table->json('weight_target')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table): void {
            $table->dropColumn(['nutrient_operators', 'weight_target']);
        });
    }
};
