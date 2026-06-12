<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement Phase 3: decouple from food_items, add the suggested-list link,
 * OR number, and multi-file PO attachments.
 *
 *  - shopping_list_items / purchase_order_items: food_item_id → fs_item_id (fs_items).
 *  - shopping_lists: + menu_cycle_id (suggested-from cycle) + days_span.
 *  - purchase_orders: + or_number.
 *  - new purchase_order_attachments (receipt | proof photos).
 *
 * Procurement data is disposable (stub) → truncate to reshape FKs cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('shopping_list_items')->truncate();
        DB::table('purchase_order_items')->truncate();
        DB::table('shopping_lists')->truncate();
        DB::table('purchase_orders')->truncate();
        Schema::enableForeignKeyConstraints();

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropForeign(['food_item_id']);
            $table->dropColumn('food_item_id');
            $table->foreignId('fs_item_id')->nullable()->after('shopping_list_id')->constrained('fs_items')->nullOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['food_item_id']);
            $table->dropColumn('food_item_id');
            $table->foreignId('fs_item_id')->nullable()->after('purchase_order_id')->constrained('fs_items')->nullOnDelete();
        });

        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->foreignId('menu_cycle_id')->nullable()->after('fss_user_id')->constrained('menu_cycles')->nullOnDelete();
            $table->unsignedInteger('days_span')->nullable()->after('period_end');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('or_number')->nullable()->after('po_number');
        });

        Schema::create('purchase_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->enum('type', ['receipt', 'proof'])->default('receipt');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_attachments');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('or_number');
        });

        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropForeign(['menu_cycle_id']);
            $table->dropColumn(['menu_cycle_id', 'days_span']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['fs_item_id']);
            $table->dropColumn('fs_item_id');
            $table->foreignId('food_item_id')->nullable()->after('purchase_order_id')->constrained('food_items');
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropForeign(['fs_item_id']);
            $table->dropColumn('fs_item_id');
            $table->foreignId('food_item_id')->nullable()->after('shopping_list_id')->constrained('food_items');
        });
    }
};
