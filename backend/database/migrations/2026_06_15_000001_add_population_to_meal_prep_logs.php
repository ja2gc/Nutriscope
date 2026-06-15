<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_prep_logs', function (Blueprint $table) {
            // Headcount actually served that day (population changes daily; the cycle's
            // population is only a default). Drives budget-per-head-per-day from real data.
            $table->unsignedInteger('population')->nullable()->after('service_date');
        });
    }

    public function down(): void
    {
        Schema::table('meal_prep_logs', function (Blueprint $table) {
            $table->dropColumn('population');
        });
    }
};
