<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {

            $table->id();
            $table->foreignId('food_item_id')->constrained('food_items');
            $table->decimal('quantity_in_stock', 10, 2)->default(0);
            $table->string('unit');
            $table->date('expiry_date')->nullable();
            $table->decimal('usage_rate', 8, 2)->nullable();
            $table->decimal('minimum_stock_threshold', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('food_item_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
