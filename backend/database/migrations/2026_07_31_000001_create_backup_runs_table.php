<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('state', 32)->index();
            $table->string('source', 16)->index();
            $table->string('storage_disk', 64);
            $table->string('object_key', 1024)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('integrity_value', 128)->nullable();
            $table->boolean('encrypted')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('recoverable_until')->nullable()->index();
            $table->timestamp('purged_at')->nullable();
            $table->string('retention_tier', 16)->nullable();
            $table->timestamp('retention_expires_at')->nullable()->index();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
