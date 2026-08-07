<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table): void {
            $table->string('archive_sha256', 64)->nullable()->after('integrity_value');
            $table->timestamp('recovery_tested_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('backup_runs', fn (Blueprint $table) => $table->dropColumn(['archive_sha256', 'recovery_tested_at']));
    }
};
