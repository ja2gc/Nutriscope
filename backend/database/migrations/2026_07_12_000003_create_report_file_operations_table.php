<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_file_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_scope', 16)->default('report');
            $table->string('operation', 32);
            $table->string('phase', 16)->default('finalized');
            $table->timestamp('available_at')->useCurrent();
            $table->string('original_path');
            $table->string('quarantine_path')->unique();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code', 160)->nullable();
            $table->timestamps();
            $table->index(['asset_scope', 'operation', 'id']);
            $table->index(['phase', 'available_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_file_operations');
    }
};
