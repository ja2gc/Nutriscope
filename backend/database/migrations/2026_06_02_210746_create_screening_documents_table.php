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
        Schema::create('screening_documents', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('assessment_id')->nullable()->constrained();
            $table->enum('type', ['adult', 'pediatric']);
            $table->string('file_path');
            $table->json('extracted_data')->nullable();
            $table->json('mapped_fields')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->foreignId('reviewed_by')->nullable()->references('id')->on('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_documents');
    }
};
