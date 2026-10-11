<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->decimal('qty', 14, 6)->change();
            $table->decimal('baseline_quantity', 14, 6)->nullable()->change();
            $table->decimal('scaled_quantity', 14, 6)->nullable()->change();
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('qty', 14, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->decimal('qty', 12, 3)->change();
            $table->decimal('baseline_quantity', 12, 3)->nullable()->change();
            $table->decimal('scaled_quantity', 12, 3)->nullable()->change();
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('qty', 10, 2)->change();
        });
    }
};
