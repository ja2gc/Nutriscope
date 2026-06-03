<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncp_record_id')->constrained()->cascadeOnDelete();
            $table->enum('domain', ['NI', 'NC', 'NB']);
            $table->string('problem');
            $table->string('label')->nullable();
            $table->text('etiology');
            $table->text('signs_symptoms');
            $table->text('pes_statement');
            $table->text('extra_notes')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
