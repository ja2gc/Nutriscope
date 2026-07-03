<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Every model reachable via {param} implicit route-model binding in routes/api.php.
     * Adding a non-sequential public identifier here closes ID-enumeration (IDOR)
     * without touching the bigint primary key or any foreign key column.
     */
    private array $tables = [
        'patients', 'ncp_records', 'screening_documents', 'diagnoses',
        'meal_plans', 'meal_plan_days', 'meal_plan_items', 'meal_plan_templates',
        'monitorings', 'reports', 'report_templates', 'purchase_orders',
        'purchase_order_vendor_groups', 'purchase_order_attachments', 'inventory',
        'shopping_lists', 'shopping_list_items', 'menu_cycles', 'fs_items',
        'food_service_recipes', 'budgets', 'suppliers', 'menu_cycle_templates',
        'meal_prep_logs', 'announcements', 'notifications', 'users',
        'food_items', 'recipes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('uuid')->nullable()->after('id');
            });
        }

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('uuid')->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                });
        }

        // Laravel 11+ alters columns natively (no doctrine/dbal needed) on MySQL/SQLite/Postgres.
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('uuid')->nullable(false)->change();
            });
        }

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('uuid'));
        }
    }
};
