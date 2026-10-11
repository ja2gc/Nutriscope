<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['shopping_list_items', 'purchase_order_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $unitPrice = $table->decimal('unit_price', 14, 6);
                if ($tableName === 'shopping_list_items') {
                    $unitPrice->nullable();
                }
                $unitPrice->change();
                $table->decimal('purchase_price', 14, 6)->nullable()->change();
                $table->decimal('purchase_qty', 14, 6)->nullable()->change();
            });
        }
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('actual_unit_price', 14, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (['shopping_list_items', 'purchase_order_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $unitPrice = $table->decimal('unit_price', 10, 2);
                if ($tableName === 'shopping_list_items') {
                    $unitPrice->nullable();
                }
                $unitPrice->change();
                $table->decimal('purchase_price', 10, 2)->nullable()->change();
                $table->decimal('purchase_qty', 10, 2)->nullable()->change();
            });
        }
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('actual_unit_price', 12, 2)->nullable()->change();
        });
    }
};
