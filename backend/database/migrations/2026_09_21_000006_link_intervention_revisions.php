<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitorings', function (Blueprint $table) {
            $table->foreignId('intervention_revision_id')
                ->nullable()
                ->after('ncp_record_id')
                ->constrained('intervention_revisions')
                ->nullOnDelete();
        });

        Schema::table('meal_plans', function (Blueprint $table) {
            $table->foreignId('intervention_revision_id')
                ->nullable()
                ->after('intervention_id')
                ->constrained('intervention_revisions')
                ->nullOnDelete();
        });

        DB::table('monitorings')->orderBy('id')->chunkById(100, function ($monitorings): void {
            foreach ($monitorings as $monitoring) {
                $revisionId = DB::table('intervention_revisions')
                    ->join('interventions', 'interventions.id', '=', 'intervention_revisions.intervention_id')
                    ->where('interventions.ncp_record_id', $monitoring->ncp_record_id)
                    ->orderByDesc('intervention_revisions.version')
                    ->value('intervention_revisions.id');

                if ($revisionId !== null) {
                    DB::table('monitorings')->where('id', $monitoring->id)->update([
                        'intervention_revision_id' => $revisionId,
                    ]);
                }
            }
        });

        DB::table('meal_plans')->orderBy('id')->chunkById(100, function ($mealPlans): void {
            foreach ($mealPlans as $mealPlan) {
                $revisionId = DB::table('intervention_revisions')
                    ->where('intervention_id', $mealPlan->intervention_id)
                    ->orderByDesc('version')
                    ->value('id');

                if ($revisionId !== null) {
                    DB::table('meal_plans')->where('id', $mealPlan->id)->update([
                        'intervention_revision_id' => $revisionId,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intervention_revision_id');
        });

        Schema::table('monitorings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intervention_revision_id');
        });
    }
};
