<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_manifest_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('backup_manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stored_object_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('stored_object_uuid');
            $table->string('protected_key', 512);
            $table->string('purpose', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('sha256', 64);
            $table->timestamps();
            $table->unique(['backup_manifest_id', 'stored_object_uuid'], 'manifest_object_unique');
            $table->index('protected_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_manifest_objects');
    }
};
