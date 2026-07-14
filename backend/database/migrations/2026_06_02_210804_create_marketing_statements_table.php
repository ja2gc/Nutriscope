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
        Schema::create('marketing_statements', function (Blueprint $table) {

            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->references('id')->on('purchase_orders');
            $table->date('date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('subtotal_forwarded', 10, 2)->nullable();
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->string('buyer_name')->nullable();
            $table->string('buyer_title')->nullable();
            $table->string('certified_by')->nullable();
            $table->string('certified_by_title')->nullable();
            $table->string('examined_by')->nullable();
            $table->string('examined_by_title')->nullable();
            $table->string('verified_by')->nullable();
            $table->string('verified_by_title')->nullable();
            $table->string('file_path')->nullable();
            $table->json('extracted_data')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_statements');
    }
};
