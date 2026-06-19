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
        // NCP supporting-document attachments (rnd.md §3.1) — plain file storage
        // linked to an NCP cycle via its assessment. No OCR/extraction.
        Schema::create('screening_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('assessment_id')->nullable()->constrained();
            $table->string('type')->nullable();           // free category: screening | labs | referral
            $table->string('file_path');
            $table->string('original_name')->nullable();   // client filename for display
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
