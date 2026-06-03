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
        Schema::create('ocr_documents', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('assessment_id')->nullable()->constrained();
            $table->string('file_path');
            $table->longText('extracted_text')->nullable();
            $table->enum('document_type', ['screening', 'lab']);
            $table->foreignId('extraction_template_id')->nullable();
            $table->json('parsed_fields')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocr_documents');
    }
};
