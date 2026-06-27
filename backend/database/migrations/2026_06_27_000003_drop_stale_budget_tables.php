<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('budget_daily_logs');
        Schema::dropIfExists('budget_adjustments');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Stub — columns were already removed, cannot fully restore without data
        DB::statement('CREATE TABLE IF NOT EXISTS budget_daily_logs (id bigint unsigned primary key auto_increment, budget_id bigint unsigned, created_at timestamp null, updated_at timestamp null)');
        DB::statement('CREATE TABLE IF NOT EXISTS budget_adjustments (id bigint unsigned primary key auto_increment, budget_id bigint unsigned, created_at timestamp null, updated_at timestamp null)');
    }
};
