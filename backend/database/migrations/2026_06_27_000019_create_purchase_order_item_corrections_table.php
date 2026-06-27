<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_item_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->decimal('old_unit_price', 10, 2);
            $table->decimal('new_unit_price', 10, 2);
            $table->decimal('old_purchase_price', 10, 2)->nullable();
            $table->decimal('new_purchase_price', 10, 2)->nullable();
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('purchase_order_item_id');
            $table->index('corrected_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item_corrections');
    }
};
