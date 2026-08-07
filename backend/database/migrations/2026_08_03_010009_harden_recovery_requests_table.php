<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recovery_requests', function (Blueprint $table): void {
            $table->foreignId('safety_snapshot_backup_run_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $table->string('temporary_database', 64)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->timestamp('safety_snapshot_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('recovery_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('safety_snapshot_backup_run_id');
            $table->dropColumn(['temporary_database', 'failure_message', 'started_at', 'terminal_at', 'safety_snapshot_expires_at']);
        });
    }
};
