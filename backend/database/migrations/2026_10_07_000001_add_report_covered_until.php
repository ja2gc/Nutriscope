<?php

use App\Services\Reports\ReportCoveredDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->date('report_covered_until')->nullable()->index();
        });

        DB::table('reports')
            ->whereIn('type', ['procurement_pack', 'program_project_activity', 'menu_calendar', 'accomplishment_report'])
            ->orderBy('id')
            ->chunkById(100, function ($reports): void {
                foreach ($reports as $report) {
                    $parameters = json_decode($report->parameters ?? '{}', true);
                    if (! is_array($parameters)) {
                        continue;
                    }
                    $coveredUntil = ReportCoveredDate::forParameters($report->type, $parameters);
                    if ($coveredUntil !== null) {
                        DB::table('reports')->where('id', $report->id)->update(['report_covered_until' => $coveredUntil]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('report_covered_until');
        });
    }
};
