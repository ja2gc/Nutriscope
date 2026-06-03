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
        Schema::create('inspection_reports', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('procurement_id')->nullable()->references('id')->on('purchase_orders');
            $table->string('supplier_name')->nullable();
            $table->string('air_no')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('po_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('requisition_office')->nullable();
            $table->date('date_received')->nullable();
            $table->date('date_inspected')->nullable();
            $table->enum('inspection_status', ['complete', 'partial'])->nullable();
            $table->string('inspected_by')->nullable();
            $table->string('inspected_by_title')->nullable();
            $table->string('certified_by')->nullable();
            $table->string('certified_by_title')->nullable();
            $table->string('verified_by')->nullable();
            $table->string('verified_by_title')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('approved_by_title')->nullable();
            $table->string('conforme_name')->nullable();
            $table->string('conforme_title')->nullable();
            $table->string('oic_pgso_name')->nullable();
            $table->string('oic_pgso_title')->nullable();
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
        Schema::dropIfExists('inspection_reports');
    }
};
