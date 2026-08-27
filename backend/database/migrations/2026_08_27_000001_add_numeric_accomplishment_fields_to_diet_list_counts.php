<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diet_list_counts', function (Blueprint $table): void {
            $table->unsignedInteger('collected_ward_diet_lists')->nullable();
            $table->unsignedInteger('apportioned_distributed_meals')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('diet_list_counts', function (Blueprint $table): void {
            $table->dropColumn(['collected_ward_diet_lists', 'apportioned_distributed_meals']);
        });
    }
};
