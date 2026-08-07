<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_run_id')->constrained()->restrictOnDelete();
            $table->string('state', 24)->index();
            $table->json('checks')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable()->index();
            $table->string('failure_message', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_tests');
    }
};
