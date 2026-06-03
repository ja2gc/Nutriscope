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
        Schema::create('extraction_templates', function (Blueprint $table) {
            
            $table->id();
            $table->enum('document_type', ['screening_adult', 'screening_pediatric', 'lab_result']);
            $table->json('field_mappings');
            $table->json('validation_rules')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['document_type', 'is_active']);
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extraction_templates');
    }
};
