<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncp_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rnd_user_id')->constrained('users');
            $table->enum('type', ['new', 'followup', 'reassessment'])->default('new');
            $table->enum('status', ['draft', 'active', 'completed', 'discharged'])->default('draft');
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncp_records');
    }
};
