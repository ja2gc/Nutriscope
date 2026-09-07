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
        Schema::create('ncp_appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ncp_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rnd_user_id')->constrained('users');
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('ncp_appointments')->nullOnDelete();
            $table->string('source', 16);
            $table->string('status', 24)->default('scheduled');
            $table->string('purpose', 255);
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('reason_code', 48)->nullable();
            $table->json('worked_on')->nullable();
            $table->json('newly_completed')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['rnd_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ncp_appointments');
    }
};
