<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE shopping_lists MODIFY status ENUM('draft','finalized','converted') NOT NULL DEFAULT 'draft'");
        DB::table('shopping_lists')->where('status', 'finalized')->update(['status' => 'converted']);
        DB::statement("ALTER TABLE shopping_lists MODIFY status ENUM('draft','converted') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE shopping_lists MODIFY status ENUM('draft','finalized','converted') NOT NULL DEFAULT 'draft'");
        DB::table('shopping_lists')->where('status', 'converted')->update(['status' => 'finalized']);
        DB::statement("ALTER TABLE shopping_lists MODIFY status ENUM('draft','finalized') NOT NULL DEFAULT 'draft'");
    }
};
