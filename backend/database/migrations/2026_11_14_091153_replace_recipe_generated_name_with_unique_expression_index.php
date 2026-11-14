<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE food_service_recipes
                DROP INDEX food_service_recipes_active_name_unique,
                DROP COLUMN active_name,
                ADD UNIQUE INDEX food_service_recipes_active_name_unique
                    ((CASE WHEN deleted_at IS NULL THEN name ELSE NULL END))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE food_service_recipes
                DROP INDEX food_service_recipes_active_name_unique,
                ADD COLUMN active_name VARCHAR(255)
                    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN name ELSE NULL END) VIRTUAL,
                ADD UNIQUE INDEX food_service_recipes_active_name_unique (active_name)
            SQL);
    }
};
