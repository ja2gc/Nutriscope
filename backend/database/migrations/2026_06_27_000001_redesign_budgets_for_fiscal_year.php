<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop known FK constraints by name (Laravel 11 no longer has Doctrine)
        $fksToDrop = ['budgets_menu_cycle_id_foreign', 'budgets_rnd_user_id_foreign'];
        foreach ($fksToDrop as $fk) {
            try {
                DB::statement("ALTER TABLE `budgets` DROP FOREIGN KEY `{$fk}`");
            } catch (Throwable) {
                // FK may not exist
            }
        }

        Schema::table('budgets', function (Blueprint $table) {
            $stale = ['rnd_user_id', 'menu_cycle_id', 'scope', 'name', 'actual_amount',
                'period_start', 'period_end', 'cost_per_person', 'population',
                'budget_per_head_day', 'budget_per_head_month', 'budget_per_head_year'];

            $existing = Schema::getColumnListing('budgets');
            $toDrop = array_values(array_intersect($stale, $existing));
            if ($toDrop) {
                $table->dropColumn($toDrop);
            }

            if (! in_array('fiscal_year', Schema::getColumnListing('budgets'))) {
                $table->unsignedSmallInteger('fiscal_year')->unique()->after('id');
            }
            if (! in_array('per_head_day_limit', Schema::getColumnListing('budgets'))) {
                $table->decimal('per_head_day_limit', 10, 2)->nullable()->after('allocated_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['fiscal_year']);
            $table->dropColumn(['fiscal_year', 'per_head_day_limit']);
            $table->string('scope')->default('custom');
            $table->decimal('allocated_amount', 15, 2)->change();
        });
    }
};
