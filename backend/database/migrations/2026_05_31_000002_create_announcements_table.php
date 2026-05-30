<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('category', ['General', 'Event', 'Operational', 'Urgent'])->default('General');
            $table->longText('attachment')->nullable();
            $table->boolean('pinned')->default(false);
            $table->enum('visibility', ['FSS', 'Admin', 'All'])->default('All');
            $table->timestamps();

            $table->index(['visibility', 'pinned', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
