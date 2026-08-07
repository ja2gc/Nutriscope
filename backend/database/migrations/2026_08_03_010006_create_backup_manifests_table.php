<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_manifests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('storage_disk', 64);
            $table->string('object_key', 512)->unique();
            $table->string('sha256', 64);
            $table->unsignedInteger('object_count');
            $table->unsignedBigInteger('total_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_manifests');
    }
};
