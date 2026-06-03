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
        Schema::create('extraction_logs', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('screening_document_id')->nullable();
            $table->foreignId('ocr_document_id')->nullable();
            $table->enum('source_type', ['screening', 'lab']);
            $table->longText('raw_text')->nullable();
            $table->json('parsed_fields')->nullable();
            $table->json('confidence_scores')->nullable();
            $table->json('errors')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extraction_logs');
    }
};
