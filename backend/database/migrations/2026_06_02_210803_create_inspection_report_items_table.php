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
        Schema::create('inspection_report_items', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('inspection_report_id')->constrained()->cascadeOnDelete();
            $table->integer('item_no')->nullable();
            $table->string('unit')->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->foreignId('food_item_id')->nullable()->references('id')->on('food_items');
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_report_items');
    }
};
