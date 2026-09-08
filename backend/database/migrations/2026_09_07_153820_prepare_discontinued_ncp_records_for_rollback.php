<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rollback safeguard only. Forward data stays unchanged.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ncp_records')
            ->where('status', 'discontinued')
            ->update(['status' => 'active', 'discontinuation_reason_code' => null]);
    }
};
