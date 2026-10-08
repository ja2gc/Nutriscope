<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_archive_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('report_retired_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 80);
            $table->char('source_key', 64)->unique();
            $table->timestamp('retired_at');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('retention_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['archived_at', 'retention_expires_at']);
        });
        Schema::dropIfExists('report_archive_settings');
        Schema::dropIfExists('report_retired_sources');
    }
};
