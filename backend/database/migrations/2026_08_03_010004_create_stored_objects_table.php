<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_objects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('storage_disk', 64);
            $table->string('object_key', 512)->unique();
            $table->string('purpose', 64)->index();
            $table->string('mime_type', 128);
            $table->string('extension', 16);
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64)->index();
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_objects');
    }
};
