<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fs_items', function (Blueprint $table): void {
            $table->softDeletes();
        });
        Schema::table('food_service_recipes', function (Blueprint $table): void {
            $table->softDeletes();
            $table->dropUnique(['name']);
            $table->string('active_name')->nullable()
                ->virtualAs('case when deleted_at is null then name else null end')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('food_service_recipes')->select('name')->groupBy('name')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Recipe names are shared with deleted records. Keep the deletion history before rolling back this migration.');
        }
        Schema::table('food_service_recipes', function (Blueprint $table): void {
            $table->dropUnique(['active_name']);
            $table->dropColumn('active_name');
            $table->dropSoftDeletes();
            $table->unique('name');
        });
        Schema::table('fs_items', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
