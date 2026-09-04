<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('backup_runs')
            ->where('source', 'manual')
            ->where('state', 'completed')
            ->update(['retention_expires_at' => null]);
    }

    public function down(): void
    {
        // Previous expiration dates cannot be reconstructed safely.
    }
};
