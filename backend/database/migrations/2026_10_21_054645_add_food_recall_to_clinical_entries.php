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
        foreach (['assessments', 'monitorings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('food_recall')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['assessments', 'monitorings'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('food_recall');
            });
        }
    }
};
