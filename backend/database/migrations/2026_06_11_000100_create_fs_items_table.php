<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Food-service catalog. Standalone — NO relation to `food_items`. Holds both
 * edible ingredients and non-food supplies (split by `kind`). No nutrition data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('kind', ['ingredient', 'supply'])->default('ingredient');
            $table->string('category')->nullable();

            // base_unit = what recipes/stock count in; purchase_unit = how it's bought.
            $table->string('base_unit')->default('g');
            $table->string('purchase_unit')->default('kg');
            $table->decimal('purchase_price', 10, 2)->default(0);   // ₱ per purchase_unit
            $table->decimal('units_per_purchase', 10, 2)->nullable(); // only for count packs (pack→pc)

            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['kind', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_items');
    }
};
