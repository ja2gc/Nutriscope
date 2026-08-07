<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('source_fingerprint', 64)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('template_version', 64)->nullable();
            $table->string('appearance_version', 32)->nullable();
            $table->string('cache_path', 512)->nullable();
            $table->timestamp('cache_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn([
            'source_fingerprint', 'content_hash', 'template_version', 'appearance_version', 'cache_path', 'cache_expires_at',
        ]));
    }
};
