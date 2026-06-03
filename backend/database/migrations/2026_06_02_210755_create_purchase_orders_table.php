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
        Schema::create('purchase_orders', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('rnd_user_id')->references('id')->on('users');
            $table->foreignId('shopping_list_id')->nullable()->references('id')->on('shopping_lists');
            $table->foreignId('supplier_id')->nullable()->references('id')->on('suppliers');
            $table->string('po_number')->unique();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['draft', 'ordered', 'received'])->default('draft');
            $table->text('receipt_image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
