<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec 6 #4: procurement is presented/rounded in whole PURCHASE units.
     * These columns are the vendor-facing view (what AIR/Statement print, what
     * the buyer orders). Base qty/unit/unit_price stay the source of truth for
     * stock + cost. Nullable + back-filled lazily for in-flight rows.
     */
    public function up(): void
    {
        foreach (['shopping_list_items', 'purchase_order_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('purchase_qty', 10, 2)->nullable()->after('unit');
                $t->string('purchase_unit')->nullable()->after('purchase_qty');
                $t->decimal('purchase_price', 10, 2)->nullable()->after('purchase_unit');
            });
        }
    }

    public function down(): void
    {
        foreach (['shopping_list_items', 'purchase_order_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['purchase_qty', 'purchase_unit', 'purchase_price']);
            });
        }
    }
};
