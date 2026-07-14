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
        Schema::create('marketing_statement_items', function (Blueprint $table) {

            $table->id();
            $table->foreignId('marketing_statement_id')->constrained()->cascadeOnDelete();
            $table->string('item_description');
            $table->string('unit')->nullable();
            $table->decimal('qty', 8, 2)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_value', 10, 2)->nullable();
            $table->string('record_payment')->nullable();
            $table->foreignId('food_item_id')->nullable()->references('id')->on('food_items');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_statement_items');
    }
};
