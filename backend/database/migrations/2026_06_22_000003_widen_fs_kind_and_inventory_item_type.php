<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make fs_items.kind and inventory.item_type plain strings instead of fixed enums.
 *
 *  - Adds a third edible kind: 'ready_to_eat' (standalone snacks like Yakult, banana,
 *    coffee) so they can be placed directly into any menu-cycle meal slot, distinct
 *    from raw 'ingredient' and non-food 'supply'.
 *  - Fixes inventory.item_type, whose old enum ('food_item','recipe') never matched the
 *    values actually written ('ingredient'/'supply'/'recipe').
 *
 * Done as a varchar widen so no future value needs a schema change. SQLite (tests)
 * keeps its existing string column; only MySQL carries a true ENUM to rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fs_items MODIFY kind VARCHAR(20) NOT NULL DEFAULT 'ingredient'");
            DB::statement("ALTER TABLE inventory MODIFY item_type VARCHAR(20) NOT NULL DEFAULT 'ingredient'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fs_items MODIFY kind ENUM('ingredient','supply') NOT NULL DEFAULT 'ingredient'");
            DB::statement("ALTER TABLE inventory MODIFY item_type ENUM('food_item','recipe') NOT NULL DEFAULT 'food_item'");
        }
    }
};
