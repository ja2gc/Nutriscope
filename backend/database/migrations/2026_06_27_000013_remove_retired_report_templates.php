<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Report types retired by the food-service redesign. */
    private const RETIRED_TYPES = [
        'dietary_cash_book',
        'budget_report',
        'inventory_report',
        'budget',
        'inventory',
    ];

    public function up(): void
    {
        DB::table('report_templates')
            ->whereIn('type', self::RETIRED_TYPES)
            ->delete();
    }

    public function down(): void
    {
        // Retired templates are re-seedable via ReportTemplateSeeder if ever needed.
    }
};
