<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->foreignId('snapshot_purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->after('fs_item_id');
            $table->json('po_snapshot')->nullable()->after('snapshot_purchase_order_id');
            $table->timestamp('po_snapshot_at')->nullable()->after('po_snapshot');
            $table->boolean('po_snapshot_locked')->default(false)->after('po_snapshot_at');
            $table->index('snapshot_purchase_order_id');
            $table->index('menu_cycle_id');
        });
    }

    public function down(): void
    {
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->dropIndex(['snapshot_purchase_order_id', 'menu_cycle_id']);
            $table->dropForeignIdFor('snapshot_purchase_order_id');
            $table->dropColumn(['snapshot_purchase_order_id', 'po_snapshot', 'po_snapshot_at', 'po_snapshot_locked']);
        });
    }
};
