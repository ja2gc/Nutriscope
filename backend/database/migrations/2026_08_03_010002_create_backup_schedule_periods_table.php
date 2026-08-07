<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedule_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_run_id')->constrained()->cascadeOnDelete();
            $table->string('category', 16);
            $table->string('period_key', 16);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['category', 'period_key']);
            $table->index(['backup_run_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedule_periods');
    }
};
